<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Unit\Concerns;

use Mockery;
use Onramplab\LaravelLogEnhancement\Tests\TestCase;
use Onramplab\LaravelLogEnhancement\Logger;
use Monolog\Logger as Monolog;

class LoggerTest extends TestCase
{
    /**
     * @test
     *
     * @return void
     */
    public function log_should_include_class_path_and_uuid_in_context()
    {
        $monolog = Mockery::mock(Monolog::class);
        $logger = new Logger($monolog);

        $monolog->shouldReceive('isHandling')->andReturn(true);
        $monolog->shouldReceive('info')->withArgs(function ($message, $context) {
            return $message === '123'
                && isSet($context['class_path'])
                && isSet($context['tracking_id']);
        })->once();

        $logger->info('123');
    }
}
