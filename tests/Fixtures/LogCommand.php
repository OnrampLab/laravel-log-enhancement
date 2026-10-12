<?php

namespace Onramplab\LaravelLogEnhancement\Tests\Fixtures;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LogCommand extends Command
{
    protected $signature = 'fixture:log';

    public function handle()
    {
        Log::info('message');

        return 0;
    }
}
