<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Unit;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Facade;
use Illuminate\Queue\Events\JobProcessing;
use Onramplab\LaravelLogEnhancement\Tests\TestCase;
use Onramplab\LaravelLogEnhancement\LaravelLogEnhancementServiceProvider;

class QueuePropagationTest extends TestCase
{
    /**
     * @test
     */
    public function it_injects_trace_id_into_queue_payload()
    {
        App::instance('trace-id', 'test-trace-id-123');

        // Use a real driver (Sync) to test payload generation
        $queue = App::make('queue')->connection('sync');
        
        $reflection = new \ReflectionClass($queue);
        $method = $reflection->getMethod('createPayload');
        $method->setAccessible(true);

        $payload = $method->invoke($queue, new \stdClass(), 'test-queue', []);
        $payloadData = json_decode($payload, true);

        $this->assertArrayHasKey('trace_id', $payloadData);
        $this->assertEquals('test-trace-id-123', $payloadData['trace_id']);
    }

    /**
     * @test
     */
    public function it_restores_trace_id_before_job_processing()
    {
        // 1. Clear existing trace-id
        App::forgetInstance('trace-id');
        $this->assertFalse(App::bound('trace-id'));

        // 2. Mock a job with a payload containing our trace_id
        $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $job->shouldReceive('payload')->andReturn([
            'trace_id' => 'restored-trace-id-456'
        ]);

        $event = new JobProcessing('connection-name', $job);

        // 3. Manually trigger the listener logic that would normally be registered via Queue::before
        // Since we are in a test context, we can call the registerHooks logic or 
        // simulate the event dispatch if the provider is already booted.
        
        // The provider is booted in TestCase::getPackageProviders
        event($event);

        // 4. Assert trace-id is restored in the container
        $this->assertTrue(App::bound('trace-id'));
        $this->assertEquals('restored-trace-id-456', App::make('trace-id'));
    }
}
