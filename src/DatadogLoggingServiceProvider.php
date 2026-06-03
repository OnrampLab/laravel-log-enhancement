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
        //
    }

    /**
     * Bootstrap any application services.
     *
     * The ddtrace/Monolog-processor wiring lives in boot(), NOT register().
     *
     * WHY boot() and not register(): the wiring calls `logger()->getLogger()`, which
     * forces the `log` singleton — and therefore the whole logging stack — to be
     * resolved, built and cached. Doing that in register() runs it before every other
     * provider has registered and before config (e.g. config:cache values, the logging
     * channel config the package's LogManager reads) is guaranteed to be in place, so
     * the logger gets built from incomplete state and that broken instance is then
     * cached for the rest of the request. This only manifests where the `ddtrace`
     * extension is present: register() returns early when `\DDTrace\current_context`
     * is undefined, so locally (no extension) the early-resolution branch is never
     * taken, but on staging (extension present) it is — which is exactly why logs went
     * missing in Datadog only on staging. boot() runs after all providers have
     * registered and config is settled, so resolving the log stack here is safe.
     *
     * @return void
     */
    public function boot()
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
                    'trace_id' => $context['trace_id'] ?? null,
                    'span_id'  => $context['span_id'] ?? null,
                ];

                return $record;
            });
        }
    }
}
