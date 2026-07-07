<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Infrastructure\Config;

use Proxynth\Larawebhook\Processing\Application\Data\RetryPolicy;
use Proxynth\Larawebhook\Processing\Application\Ports\RetryPolicyResolver;

final class ConfigRetryPolicyResolver implements RetryPolicyResolver
{
    public function resolve(): RetryPolicy
    {
        $maxAttempts = max(1, (int) config('larawebhook.retries.max_attempts', 3));

        $configuredDelays = config('larawebhook.retries.delays', [1, 5, 10]);

        $delays = is_array($configuredDelays)
            ? array_values(array_map('intval', $configuredDelays))
            : [1, 5, 10];

        return new RetryPolicy(
            maxAttempts: $maxAttempts,
            delays: $delays !== [] ? $delays : [0],
        );
    }
}
