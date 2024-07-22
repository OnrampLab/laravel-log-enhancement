<?php

namespace Onramplab\LaravelLogEnhancement\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Inject the trace ID and span ID to connect the log entry with the APM trace
 */
class DatadogProcessor implements ProcessorInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        if (!$this->isContextExisted()) {
            return $record;
        }

        $context = $this->getContext();
        $record->extra['dd'] = [
            'trace_id' => $context['trace_id'],
            'span_id' => $context['span_id'],
        ];

        return $record;
    }

    public function isContextExisted(): bool
    {
        // can get function after installing Datadog php extension
        return function_exists('\DDTrace\current_context');
    }

    /**
     * @return array{trace_id: string, span_id: string, version: string, env: string}
     */
    public function getContext(): array
    {
        return \DDTrace\current_context();
    }
}
