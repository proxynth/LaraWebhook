<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Timing;

use Proxynth\Larawebhook\Shared\Application\Ports\Delay;

final class SleepDelay implements Delay
{
    public function seconds(int $seconds): void
    {
        if ($seconds > 0) {
            sleep($seconds);
        }
    }
}
