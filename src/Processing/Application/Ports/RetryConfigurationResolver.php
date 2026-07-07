<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Ports;

use Proxynth\Larawebhook\Processing\Application\Data\RetryConfiguration;

interface RetryConfigurationResolver
{
    public function resolve(): RetryConfiguration;
}
