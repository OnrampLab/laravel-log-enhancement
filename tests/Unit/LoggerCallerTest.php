<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Unit;

use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;
use Onramplab\LaravelLogEnhancement\Logger;
use Onramplab\LaravelLogEnhancement\Tests\TestCase;

/**
 * A plain function frame has no `class` key. It is the frame Logger inspects
 * to find the caller, so it exercises the missing-class guard.
 */
function log_from_plain_function(Logger $logger): void
{
    $logger->info('from a plain function');
}

class LoggerCallerTest extends TestCase
{
    /**
     * @test
     */
    public function class_path_is_unknown_when_caller_frame_has_no_class()
    {
        $handler = new TestHandler();
        $logger = new Logger(new Monolog('testing', [$handler]));

        log_from_plain_function($logger);

        $record = $handler->getRecords()[0];
        $this->assertSame('unknown', $record->context['class_path']);
    }

    /**
     * @test
     */
    public function class_path_is_the_calling_class_when_caller_frame_has_one()
    {
        $handler = new TestHandler();
        $logger = new Logger(new Monolog('testing', [$handler]));

        $logger->info('from a method');

        $record = $handler->getRecords()[0];
        $this->assertSame(self::class, $record->context['class_path']);
    }
}
