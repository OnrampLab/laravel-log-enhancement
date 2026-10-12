<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Fixtures;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;

/**
 * Writes one log record per method, each through a different entry point.
 */
class LogCaller
{
    use LogsViaTrait;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function viaFacade()
    {
        Log::info('message');
    }

    public static function viaStaticMethod()
    {
        Log::info('message');
    }

    public function viaInjectedPsrLogger()
    {
        $this->logger->info('message');
    }

    public function viaContainer()
    {
        app('log')->info('message');
    }

    public function viaLoggerHelper()
    {
        logger('message');
    }

    public function viaLoggerHelperInstance()
    {
        logger()->info('message');
    }

    public function viaInfoHelper()
    {
        info('message');
    }

    public function viaChannel()
    {
        Log::channel('capture')->info('message');
    }

    public function viaDriver()
    {
        Log::driver()->info('message');
    }

    public function viaStack()
    {
        Log::stack(['capture'])->info('message');
    }

    public function viaNamedStack()
    {
        Log::stack(['capture'], 'custom')->info('message');
    }

    public function viaConfiguredStack()
    {
        Log::channel('capture-stack')->info('message');
    }

    public function viaBuild()
    {
        Log::build(['driver' => 'monolog', 'handler' => TestHandler::class])->info('message');
    }

    public function viaWithContext()
    {
        Log::withContext(['shared' => 'value'])->info('message');
    }

    public function viaWrite()
    {
        Log::write('info', 'message');
    }

    public function viaLog()
    {
        Log::log('info', 'message');
    }

    public function viaLevel($level)
    {
        Log::{$level}('message');
    }

    public function viaClosure()
    {
        $write = function () {
            Log::info('message');
        };

        $write();
    }

    public function viaStaticClosure()
    {
        $write = static function () {
            Log::info('message');
        };

        $write();
    }

    public function viaCollectionCallback()
    {
        collect(['item'])->each(function () {
            Log::info('message');
        });
    }
}
