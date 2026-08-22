<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Shared\Application\Ports;

interface Delay
{
    public function seconds(int $seconds): void;
}
