<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Fixtures;

use Illuminate\Support\Facades\Log;

trait LogsViaTrait
{
    public function viaTrait()
    {
        Log::info('message');
    }
}
