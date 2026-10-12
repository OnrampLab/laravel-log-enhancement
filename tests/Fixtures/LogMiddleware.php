<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Fixtures;

use Illuminate\Support\Facades\Log;

class LogMiddleware
{
    public function handle($request, $next)
    {
        Log::info('message');

        return $next($request);
    }
}
