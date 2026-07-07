<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Data;

final readonly class RetryConfiguration
{
    public function __construct(
        public bool $enabled,
        public bool $async,
    ) {}

    public function shouldRetryAsync(): bool
    {
        return $this->enabled && $this->async;
    }
}
