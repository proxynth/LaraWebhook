<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Tests\Fakes\Processing;

use Proxynth\Larawebhook\Processing\Application\Data\RetryPolicy;
use Proxynth\Larawebhook\Processing\Application\Ports\RetryPolicyResolver;

final readonly class FakeRetryPolicyResolver implements RetryPolicyResolver
{
    public function __construct(
        private RetryPolicy $policy,
    ) {}

    public function resolve(): RetryPolicy
    {
        return $this->policy;
    }
}
