<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Fixtures;

use Illuminate\Support\Facades\Log;

class LogController
{
    public function __invoke()
    {
        Log::info('message');

        return 'ok';
    }
}
