<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Unit;

use Illuminate\Support\Facades\App;
use Onramplab\LaravelLogEnhancement\Tests\TestCase;
use Onramplab\LaravelLogEnhancement\LaravelLogEnhancementServiceProvider;
use Onramplab\LaravelLogEnhancement\Http\Middleware\TraceIdMiddleware;
use Illuminate\Contracts\Http\Kernel;
use Ramsey\Uuid\Uuid;

class ServiceProviderTest extends TestCase
{
    /**
     * @test
     */
    public function it_binds_fallback_trace_id_on_boot()
    {
        // The ServiceProvider is booted by Orchestra Testbench in the parent setup
        $this->assertTrue(App::bound('trace-id'));
        $this->assertTrue(Uuid::isValid(App::make('trace-id')));
    }

    /**
     * @test
     */
    public function it_registers_middleware_globally()
    {
        $kernel = App::make(Kernel::class);
        $this->assertTrue($kernel->hasMiddleware(TraceIdMiddleware::class));
    }
}
