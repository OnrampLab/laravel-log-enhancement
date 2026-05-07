<?php

namespace Onramplab\LaravelLogEnhancement;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Onramplab\LaravelLogEnhancement\Http\Middleware\TraceIdMiddleware;
use Ramsey\Uuid\Uuid;

class LaravelLogEnhancementServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Ensure a trace-id is always available in the container.
        // This acts as a fallback for non-HTTP contexts (CLI, Cron, Workers).
        if (!$this->app->bound('trace-id')) {
            $this->app->instance('trace-id', Uuid::uuid4()->toString());
        }

        $this->registerMiddleware();

        $this->mergeConfigFrom(__DIR__ . '/../config/LaravelLogEnhancement.php', 'laravel-log-enhancement');

        $this->publishConfig();

        // $this->loadViewsFrom(__DIR__.'/resources/views', 'laravel-log-enhancement');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->registerRoutes();
        $this->registerHooks();
    }

    protected function registerMiddleware()
    {
        // We check if Kernel is bound instead of using runningInConsole().
        // This ensures the middleware is still registered during unit tests (which run in CLI),
        // while preventing a BindingResolutionException in pure Worker or Artisan environments.
        if (!$this->app->bound(Kernel::class)) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);

        // Ensure the resolved Kernel actually supports pushing middleware (standard for Web Kernels).
        if (method_exists($kernel, 'pushMiddleware')) {
            $kernel->prependMiddleware(TraceIdMiddleware::class);
        }
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    private function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
        });
    }

    /**
    * Get route group configuration array.
    *
    * @return array
    */
    private function routeConfiguration()
    {
        return [
            'namespace'  => "Onramplab\LaravelLogEnhancement\Http\Controllers",
            'middleware' => 'api',
            'prefix'     => 'api'
        ];
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('log', function (Application $app) {
            return new LogManager($app);
        });
    }

    public function registerHooks()
    {
        // Inject trace-id as a plain string into the job payload at dispatch time.
        // We only return the 'trace_id' key because Laravel uses array_merge to combine
        // the return value of this callback with the existing payload.
        Queue::createPayloadUsing(function ($connection, $queue, $payload) {
            if ($this->app->bound('trace-id')) {
                return ['trace_id' => $this->app->make('trace-id')];
            }

            return [];
        });

        Queue::before(function (JobProcessing $event) {
            $this->app->forgetInstance('log');
            Facade::clearResolvedInstance('log');

            // Restore trace-id from the plain-string payload key injected at dispatch time.
            $traceId = data_get($event->job->payload(), 'trace_id');

            if ($traceId) {
                $this->app->instance('trace-id', $traceId);
            }
        });
    }

    /**
     * Publish Config
     *
     * @return void
     */
    public function publishConfig()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/LaravelLogEnhancement.php' => config_path('LaravelLogEnhancement.php'),
            ], 'config');
        }
    }
}
