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
        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(TraceIdMiddleware::class);
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
        // This avoids any need to unserialize the job command when restoring it.
        Queue::createPayloadUsing(function ($connection, $queue, $payload) {
            if ($this->app->bound('trace-id')) {
                $payload['trace_id'] = $this->app->make('trace-id');
            }

            return $payload;
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
