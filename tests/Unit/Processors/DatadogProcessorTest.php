<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Unit\Processors;

use Illuminate\Foundation\Testing\WithFaker;
use Mockery;
use Mockery\MockInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Onramplab\LaravelLogEnhancement\Processors\DatadogProcessor;
use Onramplab\LaravelLogEnhancement\Tests\TestCase;

class DatadogProcessorTest extends TestCase
{
    use WithFaker;

    private MockInterface $processor;

    private LogRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = Mockery::mock(DatadogProcessor::class)->makePartial();
        $this->record = new LogRecord(
            datetime: now()->toDateTimeImmutable(),
            channel: 'fake-channel',
            level: Level::Debug,
            message: 'fake message',
            context: [],
            extra: [],
        );
    }

    /**
     * @test
     */
    public function processor_should_inject_extra_data_when_context_is_existed(): void
    {
        $context = [
            'trace_id' => $this->faker->uuid(),
            'span_id' => $this->faker->uuid(),
        ];

        $this->processor
            ->shouldReceive('isContextExisted')
            ->once()
            ->andReturn(true);

        $this->processor
            ->shouldReceive('getContext')
            ->once()
            ->andReturn($context);

        $record = ($this->processor)($this->record);

        $this->assertSame($context['trace_id'], $record->extra['dd']['trace_id']);
        $this->assertSame($context['span_id'], $record->extra['dd']['span_id']);
    }

    /**
     * @test
     */
    public function processor_should_not_inject_extra_data_when_context_is_not_existed(): void
    {
        $this->processor
            ->shouldReceive('isContextExisted')
            ->once()
            ->andReturn(false);

        $record = ($this->processor)($this->record);

        $this->assertArrayNotHasKey('dd', $record->extra);
    }
}
