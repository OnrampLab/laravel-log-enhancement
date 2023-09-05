<?php

namespace Onramplab\LaravelLogEnhancement\Tests;

use Onramplab\LaravelLogEnhancement\DatadogLoggingServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;


class DatadogLoggingServiceProviderTest extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [DatadogLoggingServiceProvider::class];
    }

    /**
     * @test
     */
    public function provider_is_loaded()
    {
        $this->assertInstanceOf(DatadogLoggingServiceProvider::class, $this->app->getProvider(DatadogLoggingServiceProvider::class));
    }
}
