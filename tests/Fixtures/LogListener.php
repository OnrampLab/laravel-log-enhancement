<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Fixtures;

use Illuminate\Support\Facades\Log;

class LogListener
{
    public function handle(LogEvent $event)
    {
        Log::info('message');
    }
}
