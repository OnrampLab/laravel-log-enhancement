<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Unit;

use Illuminate\Support\Facades\App;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;
use Onramplab\LaravelLogEnhancement\Logger;
use Onramplab\LaravelLogEnhancement\Tests\TestCase;

/**
 * Regression coverage for CT-476 (trace-id propagation).
 *
 * The trace-id feature makes Logger read `app('trace-id')` to populate the
 * `tracking_id` field. The contract these tests pin down:
 *
 *  1. When `trace-id` is bound, `tracking_id` carries it.
 *  2. When `trace-id` is NOT bound (queue/CLI/early-boot), logging must NOT throw and
 *     must fall back to the per-logger debugId (a UUID) — never suppress the log.
 *  3. A malformed (non-string) binding must fall back safely, never poisoning the log.
 */
class TraceIdLoggingTest extends TestCase
{
    /**
     * @test
     */
    public function tracking_id_uses_bound_trace_id()
    {
        App::instance('trace-id', 'TRACE-FROM-REQUEST');

        $handler = new TestHandler();
        $logger = new Logger(new Monolog('testing', [$handler]));

        $logger->info('hello');

        $record = $handler->getRecords()[0];
        $this->assertSame('TRACE-FROM-REQUEST', $record['context']['tracking_id']);
    }

    /**
     * @test
     */
    public function logging_does_not_throw_and_falls_back_when_trace_id_unbound()
    {
        // Remove the fallback binding the service provider seeds on boot so we exercise
        // the genuinely-unbound (queue/CLI/early-boot) path.
        App::forgetInstance('trace-id');
        $this->assertFalse(App::bound('trace-id'));

        $handler = new TestHandler();
        $logger = new Logger(new Monolog('testing', [$handler]));

        // Must not throw even though the binding is absent.
        $logger->info('hello-without-trace-id');

        $record = $handler->getRecords()[0];
        $this->assertArrayHasKey('tracking_id', $record['context']);
        $this->assertNotEmpty($record['context']['tracking_id']);
        // Falls back to a generated debugId (a UUID), not the (missing) trace-id.
        $this->assertRegExp(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $record['context']['tracking_id']
        );
    }

    /**
     * @test
     */
    public function non_string_trace_id_binding_falls_back_safely()
    {
        // A malformed binding (e.g. an int from an APM context) must not poison the log.
        App::instance('trace-id', 12345);

        $handler = new TestHandler();
        $logger = new Logger(new Monolog('testing', [$handler]));

        $logger->info('hello-with-bad-trace-id');

        $record = $handler->getRecords()[0];
        $this->assertArrayHasKey('tracking_id', $record['context']);
        $this->assertRegExp(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $record['context']['tracking_id']
        );
    }
}
