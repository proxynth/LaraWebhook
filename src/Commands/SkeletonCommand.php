<?php

namespace Proxynth\Larawebhook\Commands;

use Illuminate\Console\Command;

class SkeletonCommand extends Command
{
    public $signature = 'signature';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}