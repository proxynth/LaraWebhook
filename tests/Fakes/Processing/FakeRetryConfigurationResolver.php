<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Tests\Fakes\Processing;

use Proxynth\Larawebhook\Processing\Application\Data\RetryConfiguration;
use Proxynth\Larawebhook\Processing\Application\Ports\RetryConfigurationResolver;

final readonly class FakeRetryConfigurationResolver implements RetryConfigurationResolver
{
    public function __construct(
        private RetryConfiguration $configuration,
    ) {}

    public function resolve(): RetryConfiguration
    {
        return $this->configuration;
    }
}
