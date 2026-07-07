<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Infrastructure\Config;

use Proxynth\Larawebhook\Processing\Application\Data\RetryConfiguration;
use Proxynth\Larawebhook\Processing\Application\Ports\RetryConfigurationResolver;

class ConfigRetryConfigurationResolver implements RetryConfigurationResolver
{
    public function resolve(): RetryConfiguration
    {
        return new RetryConfiguration(
            enabled: (bool) config('larawebhook.retries.enabled', true),
            async: (bool) config('larawebhook.retries.async', false),
        );
    }
}
