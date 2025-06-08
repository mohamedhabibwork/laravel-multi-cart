<?php

namespace HCart\LaravelMultiCart\Commands;

use Illuminate\Console\Command;

class LaravelMultiCartCommand extends Command
{
    public $signature = 'laravel-multi-cart';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
