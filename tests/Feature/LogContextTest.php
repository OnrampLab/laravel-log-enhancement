<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Onramplab\LaravelLogEnhancement\Tests\Fixtures\LogCaller;
use Onramplab\LaravelLogEnhancement\Tests\Fixtures\LogCommand;
use Onramplab\LaravelLogEnhancement\Tests\Fixtures\LogController;
use Onramplab\LaravelLogEnhancement\Tests\Fixtures\LogEvent;
use Onramplab\LaravelLogEnhancement\Tests\Fixtures\LogJob;
use Onramplab\LaravelLogEnhancement\Tests\Fixtures\LogListener;
use Onramplab\LaravelLogEnhancement\Tests\Fixtures\LogMiddleware;
use Onramplab\LaravelLogEnhancement\Tests\TestCase;
use RuntimeException;

/**
 * Locks the class_path and tracking_id added to every log record, for each
 * way an application can write a log. The expected values must stay the same
 * on every supported Laravel version.
 */
class LogContextTest extends TestCase
{
    /**
     * @var \Illuminate\Log\Events\MessageLogged[]
     */
    protected $logged = [];

    public function setUp(): void
    {
        parent::setUp();

        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logged[] = $event;
        });
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('queue.default', 'sync');
        $app['config']->set('logging.default', 'capture');
        $app['config']->set('logging.channels.capture', [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
            'formatter' => JsonFormatter::class,
        ]);
        $app['config']->set('logging.channels.second', [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
        ]);
        $app['config']->set('logging.channels.warnings', [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
            'level' => 'warning',
        ]);
        $app['config']->set('logging.channels.capture-stack', [
            'driver' => 'stack',
            'channels' => ['capture'],
        ]);
    }

    /**
     * @test
     * @dataProvider callerMethods
     *
     * @return void
     */
    public function class_path_is_the_class_that_wrote_the_log($method)
    {
        $this->app->make(LogCaller::class)->{$method}();

        $this->assertCount(1, $this->logged);
        $this->assertSame(LogCaller::class, $this->logged[0]->context['class_path']);
        $this->assertTrackingId($this->logged[0]->context['tracking_id']);
    }

    public static function callerMethods()
    {
        return [
            'Log facade' => ['viaFacade'],
            'Log facade in a static method' => ['viaStaticMethod'],
            'injected PSR logger' => ['viaInjectedPsrLogger'],
            'log manager from the container' => ['viaContainer'],
            'logger() helper with a message' => ['viaLoggerHelper'],
            'logger() helper instance' => ['viaLoggerHelperInstance'],
            'info() helper' => ['viaInfoHelper'],
            'Log::channel()' => ['viaChannel'],
            'Log::driver()' => ['viaDriver'],
            'Log::stack()' => ['viaStack'],
            'Log::stack() with a name' => ['viaNamedStack'],
            'stack channel from config' => ['viaConfiguredStack'],
            'Log::build()' => ['viaBuild'],
            'Log::withContext()' => ['viaWithContext'],
            'Log::write()' => ['viaWrite'],
            'Log::log()' => ['viaLog'],
            'closure' => ['viaClosure'],
            'static closure' => ['viaStaticClosure'],
            'collection callback' => ['viaCollectionCallback'],
            'trait method' => ['viaTrait'],
        ];
    }

    /**
     * @test
     * @dataProvider levels
     *
     * @return void
     */
    public function class_path_is_attached_on_every_level($level)
    {
        $this->app->make(LogCaller::class)->viaLevel($level);

        $this->assertCount(1, $this->logged);
        $this->assertSame($level, $this->logged[0]->level);
        $this->assertSame(LogCaller::class, $this->logged[0]->context['class_path']);
        $this->assertTrackingId($this->logged[0]->context['tracking_id']);
    }

    public static function levels()
    {
        return [
            ['emergency'],
            ['alert'],
            ['critical'],
            ['error'],
            ['warning'],
            ['notice'],
            ['info'],
            ['debug'],
        ];
    }

    /**
     * @test
     *
     * @return void
     */
    public function class_path_is_the_queued_job()
    {
        LogJob::dispatch();

        $this->assertSame([LogJob::class, LogJob::class], $this->loggedClassPaths());
    }

    /**
     * @test
     *
     * @return void
     */
    public function class_path_is_the_event_listener()
    {
        Event::listen(LogEvent::class, LogListener::class);

        event(new LogEvent());

        $this->assertSame([LogListener::class], $this->loggedClassPaths());
    }

    /**
     * @test
     *
     * @return void
     */
    public function class_path_is_the_controller()
    {
        Route::get('/log', LogController::class);

        $this->get('/log')->assertOk();

        $this->assertSame([LogController::class], $this->loggedClassPaths());
    }

    /**
     * @test
     *
     * @return void
     */
    public function class_path_is_the_middleware()
    {
        Route::get('/log', function () {
            return 'ok';
        })->middleware(LogMiddleware::class);

        $this->get('/log')->assertOk();

        $this->assertSame([LogMiddleware::class], $this->loggedClassPaths());
    }

    /**
     * @test
     *
     * @return void
     */
    public function class_path_is_the_console_command()
    {
        $this->app->make(Kernel::class)->registerCommand(new LogCommand());

        $this->assertSame(0, $this->artisan('fixture:log')->run());

        $this->assertSame([LogCommand::class], $this->loggedClassPaths());
    }

    /**
     * @test
     *
     * @return void
     */
    public function class_path_is_the_exception_handler_for_reported_exceptions()
    {
        $this->withExceptionHandling();

        report(new RuntimeException('reported'));

        $this->assertCount(1, $this->logged);
        $this->assertSame('reported', $this->logged[0]->message);
        $this->assertSame(Handler::class, $this->logged[0]->context['class_path']);
        $this->assertTrackingId($this->logged[0]->context['tracking_id']);
    }

    /**
     * @test
     *
     * @return void
     */
    public function class_path_and_tracking_id_are_appended_after_the_given_context()
    {
        Log::info('message', ['key' => 'value']);

        $this->assertSame(['key', 'class_path', 'tracking_id'], array_keys($this->logged[0]->context));
        $this->assertSame('value', $this->logged[0]->context['key']);
    }

    /**
     * @test
     *
     * @return void
     */
    public function class_path_and_tracking_id_are_appended_after_the_channel_context()
    {
        Log::withContext(['channel' => 'value'])->info('message');

        $this->assertSame(['channel', 'class_path', 'tracking_id'], array_keys($this->logged[0]->context));
        $this->assertSame('value', $this->logged[0]->context['channel']);
    }

    /**
     * @test
     *
     * @return void
     */
    public function shared_context_reaches_channels_that_are_already_resolved()
    {
        Log::channel('capture');
        Log::channel('second');

        Log::shareContext(['shared' => 'value']);

        Log::info('message');
        Log::channel('second')->info('message');

        $this->assertCount(2, $this->logged);

        foreach ($this->logged as $event) {
            $this->assertSame(['shared', 'class_path', 'tracking_id'], array_keys($event->context));
            $this->assertSame('value', $event->context['shared']);
        }
    }

    /**
     * @test
     *
     * @return void
     */
    public function shared_context_does_not_reach_loggers_created_afterwards()
    {
        // Current behaviour: LogManager::get() and stack() do not apply the
        // shared context to the loggers they create, so only channels that
        // were resolved before shareContext() carry it.
        Log::shareContext(['shared' => 'value']);

        Log::channel('second')->info('message');
        Log::stack(['second'])->info('message');

        $this->assertCount(2, $this->logged);

        foreach ($this->logged as $event) {
            $this->assertSame(['class_path', 'tracking_id'], array_keys($event->context));
        }
    }

    /**
     * @test
     *
     * @return void
     */
    public function tracking_id_is_shared_by_logs_of_the_same_channel()
    {
        Log::info('first');
        Log::info('second');

        $ids = $this->loggedTrackingIds();

        $this->assertSame($ids[0], $ids[1]);
    }

    /**
     * @test
     *
     * @return void
     */
    public function tracking_id_is_the_bound_trace_id_for_every_channel()
    {
        App::instance('trace-id', 'TRACE-FROM-REQUEST');

        Log::info('message');
        Log::channel('second')->info('message');

        $this->assertSame(['TRACE-FROM-REQUEST', 'TRACE-FROM-REQUEST'], $this->loggedTrackingIds());
    }

    /**
     * @test
     *
     * @return void
     */
    public function tracking_id_follows_the_bound_trace_id_for_each_queued_job()
    {
        App::instance('trace-id', 'TRACE-OF-JOB-A');
        LogJob::dispatch();

        App::instance('trace-id', 'TRACE-OF-JOB-B');
        LogJob::dispatch();

        $this->assertSame(
            ['TRACE-OF-JOB-A', 'TRACE-OF-JOB-A', 'TRACE-OF-JOB-B', 'TRACE-OF-JOB-B'],
            $this->loggedTrackingIds()
        );
    }

    /**
     * @test
     *
     * @return void
     */
    public function json_record_keeps_its_structure()
    {
        Log::info('message', ['key' => 'value']);

        $record = json_decode($this->captureHandler()->getRecords()[0]->formatted, true);

        $this->assertSame(
            ['message', 'context', 'level', 'level_name', 'channel', 'datetime', 'extra'],
            array_keys($record)
        );
        $this->assertSame('message', $record['message']);
        $this->assertSame(['key', 'class_path', 'tracking_id'], array_keys($record['context']));
        $this->assertSame(static::class, $record['context']['class_path']);
        $this->assertSame(200, $record['level']);
        $this->assertSame('INFO', $record['level_name']);
        $this->assertSame('testing', $record['channel']);
        $this->assertSame([], $record['extra']);
    }

    /**
     * @test
     *
     * @return void
     */
    public function named_stack_keeps_the_fallback_channel_name()
    {
        // LogManager::stack() hands the name over under the "channel" key, which
        // the stack driver does not read, so the record keeps the fallback name.
        Log::stack(['capture'], 'custom')->info('message');

        $this->assertSame('testing', $this->captureHandler()->getRecords()[0]->channel);
    }

    /**
     * @test
     *
     * @return void
     */
    public function context_facade_data_is_not_added_to_the_record()
    {
        // Current behaviour: LogManager::get() does not push Laravel's context
        // processor, so Context data never reaches the record's "extra".
        Context::add('key', 'value');

        Log::info('message');

        $record = $this->captureHandler()->getRecords()[0];

        $this->assertSame([], $record->extra);
        $this->assertSame(['class_path', 'tracking_id'], array_keys($record->context));
    }

    /**
     * @test
     *
     * @return void
     */
    public function records_below_the_channel_level_are_not_written()
    {
        Log::channel('warnings')->info('ignored');
        Log::channel('warnings')->warning('written');

        $records = Log::channel('warnings')->getLogger()->getHandlers()[0]->getRecords();

        $this->assertCount(1, $records);
        $this->assertSame('written', $records[0]->message);
        $this->assertSame(static::class, $records[0]->context['class_path']);
    }

    protected function loggedClassPaths()
    {
        return array_map(function (MessageLogged $event) {
            return $event->context['class_path'];
        }, $this->logged);
    }

    protected function loggedTrackingIds()
    {
        return array_map(function (MessageLogged $event) {
            return $event->context['tracking_id'];
        }, $this->logged);
    }

    protected function captureHandler()
    {
        return Log::channel('capture')->getLogger()->getHandlers()[0];
    }

    protected function assertTrackingId($value)
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $value
        );
    }
}
