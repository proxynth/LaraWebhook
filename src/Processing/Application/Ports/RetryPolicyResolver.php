<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Ports;

use Proxynth\Larawebhook\Processing\Application\Data\RetryPolicy;

interface RetryPolicyResolver
{
    public function resolve(): RetryPolicy;
}
