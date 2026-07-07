<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Data;

final readonly class RetryPolicy
{
    /**
     * @param  list<int>  $delays
     */
    public function __construct(
        public int $maxAttempts,
        public array $delays,
    ) {}

    public function shouldRetryAfter(int $attempt): bool
    {
        return $attempt < $this->maxAttempts - 1;
    }

    public function nextAttemptAfter(int $attempt): ?int
    {
        return $this->shouldRetryAfter($attempt)
            ? $attempt + 1
            : null;
    }

    public function delayForAttempt(int $attempt): ?int
    {
        if (! $this->shouldRetryAfter($attempt)) {
            return null;
        }

        if (isset($this->delays[$attempt])) {
            return $this->delays[$attempt];
        }

        return $this->delays !== []
            ? $this->delays[array_key_last($this->delays)]
            : 0;
    }
}
