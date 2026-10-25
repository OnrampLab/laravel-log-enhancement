<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Unit;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\TestHandler;
use Onramplab\LaravelLogEnhancement\DatadogLoggingServiceProvider;
use Onramplab\LaravelLogEnhancement\Handlers\DatadogHandler;
use Onramplab\LaravelLogEnhancement\Tests\TestCase;

/**
 * Defining \DDTrace\current_context() makes it visible to the whole PHP process
 * from then on, so every case runs in its own process and only the cases that
 * need the function load the fixture.
 */
class DatadogLoggingServiceProviderBootTest extends TestCase
{
    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function it_pushes_dd_processor_and_keeps_formatters_when_datadog_handler_is_present()
    {
        require_once __DIR__ . '/../Fixtures/ddtrace_current_context.php';

        $this->useChannels(['datadog', 'memory']);

        $this->registerAndBootProvider();

        $handler = $this->memoryHandler();
        $this->assertInstanceOf(LineFormatter::class, $handler->getFormatter());

        $this->app['log']->info('hello');

        $this->assertSame(
            ['trace_id' => 'dd-trace-1', 'span_id' => 'dd-span-1'],
            $handler->getRecords()[0]->extra['dd']
        );
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function it_does_not_push_dd_processor_without_datadog_handler()
    {
        require_once __DIR__ . '/../Fixtures/ddtrace_current_context.php';

        $this->useChannels(['memory']);

        $this->registerAndBootProvider();

        $handler = $this->memoryHandler();
        $this->assertInstanceOf(LineFormatter::class, $handler->getFormatter());

        $this->app['log']->info('hello');

        $this->assertArrayNotHasKey('dd', $handler->getRecords()[0]->extra);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function it_does_nothing_when_ddtrace_function_is_missing()
    {
        $this->assertFalse(function_exists('DDTrace\current_context'));

        $this->useChannels(['datadog', 'memory']);

        $this->registerAndBootProvider();

        $handler = $this->memoryHandler();
        $this->assertInstanceOf(LineFormatter::class, $handler->getFormatter());

        $this->app['log']->info('hello');

        $this->assertArrayNotHasKey('dd', $handler->getRecords()[0]->extra);
    }

    /**
     * @param array<int, string> $channels
     */
    private function useChannels(array $channels): void
    {
        config()->set('logging.default', 'combined');
        config()->set('logging.channels.combined', [
            'driver' => 'stack',
            'channels' => $channels,
        ]);
        config()->set('logging.channels.datadog', [
            'driver' => 'monolog',
            'handler' => FakeDatadogHandler::class,
            'with' => ['key' => 'fake-key'],
        ]);
        config()->set('logging.channels.memory', [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
            'formatter' => LineFormatter::class,
        ]);

        $this->app->forgetInstance('log');
    }

    /**
     * Mirror the application lifecycle: register() runs before boot().
     */
    private function registerAndBootProvider(): void
    {
        $provider = new DatadogLoggingServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    private function memoryHandler(): TestHandler
    {
        foreach (logger()->getLogger()->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                return $handler;
            }
        }

        $this->fail('TestHandler not found in the default channel');
    }
}

/**
 * Does not talk to the network.
 */
class FakeDatadogHandler extends DatadogHandler
{
    protected function send(string $data): void
    {
    }
}
