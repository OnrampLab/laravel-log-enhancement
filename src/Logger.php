<?php

namespace Onramplab\LaravelLogEnhancement;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Log\Logger as IlluminateLogger;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class Logger extends IlluminateLogger
{
    /**
     * @var string
     */
    protected $debugId;

    /**
     * LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $channelName;

    /**
     * Create a new log writer instance.
     *
     * @param  \Psr\Log\LoggerInterface  $logger
     * @param  \Illuminate\Contracts\Events\Dispatcher|null  $dispatcher
     * @return void
     */
    public function __construct(LoggerInterface $logger, Dispatcher $dispatcher = null)
    {
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
        $this->debugId = Uuid::uuid4()->toString();
    }

    /**
     * Write a message to the log.
     *
     * @param  string  $level
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    protected function writeLog($level, $message, $context): void
    {
        $info = $this->generateExtraContextInfo();
        $context = array_merge($context, $info);

        parent::writeLog($level, $message, $context);
    }

    protected function generateExtraContextInfo()
    {
        $info = [];

        // attach class_path
        // NOTE: it's hardcoded, should find a better way to get caller class
        $stack = debug_backtrace();
        $caller = $stack[3] ?? [];

        if (isset($caller['class']) && $caller['class'] === 'Illuminate\Log\LogManager') {
            // It means log from channel
            $caller = $stack[5] ?? $caller;
        }

        $info['class_path'] = $caller['class'] ?? 'unknown';

        // attach tracking_id — prefer app-level trace-id (set by TraceIdMiddleware or job
        // propagation). Reading the binding must NEVER throw or suppress a log line: in
        // queue/CLI/early-boot contexts the container may be in an unusual state or the
        // binding may be absent/malformed, so we always seed tracking_id with the
        // per-logger debugId first and only override it when the binding resolves to a
        // non-empty string, inside a try/catch.
        $info['tracking_id'] = $this->debugId;

        try {
            if (app()->bound('trace-id')) {
                $traceId = app('trace-id');

                if (is_string($traceId) && $traceId !== '') {
                    $info['tracking_id'] = $traceId;
                }
            }
        } catch (\Throwable $e) {
            // keep the debugId fallback
        }

        return $info;
    }
}
