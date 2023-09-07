<?php

namespace Onramplab\LaravelLogEnhancement;

use Illuminate\Support\ServiceProvider;
use Onramplab\LaravelLogEnhancement\Handlers\DatadogHandler;

class DatadogLoggingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // can get function after install php datadog-setup
        if (!function_exists('\DDTrace\current_context')) {
            return;
        }

        // Get the Monolog instance
        $monolog = logger()->getLogger();
        if (!$monolog instanceof \Monolog\Logger) {
            return;
        }

        $useDatadog = false;
        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof DatadogHandler) {
                $useDatadog = true;
            }
        }

        // Inject the trace and span ID to connect the log entry with the APM trace
        if ($useDatadog) {
            $monolog->pushProcessor(function ($record) {
                // @phpstan-ignore-next-line
                $context = \DDTrace\current_context();
                $record['extra']['dd'] = [
                    'trace_id' => $context['trace_id'],
                    'span_id'  => $context['span_id'],
                ];

                return $record;
            });
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
