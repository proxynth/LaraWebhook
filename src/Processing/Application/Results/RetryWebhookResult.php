<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Results;

use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Shared\Application\Results\Result;

final readonly class RetryWebhookResult implements Result
{
    private function __construct(
        public bool $success,
        public bool $shouldRetry,
        public WebhookLog $log,
        public int $attempt,
        public ?int $nextAttempt = null,
        public ?int $delaySeconds = null,
    ) {}

    public static function success(WebhookLog $log): self
    {
        return new self(
            success: true,
            shouldRetry: false,
            log: $log,
            attempt: $log->attempt,
        );
    }

    public static function failed(
        WebhookLog $log,
        bool $shouldRetry,
        ?int $nextAttempt = null,
        ?int $delaySeconds = null,
    ): self {
        return new self(
            success: false,
            shouldRetry: $shouldRetry,
            log: $log,
            attempt: $log->attempt,
            nextAttempt: $nextAttempt,
            delaySeconds: $delaySeconds,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailed(): bool
    {
        return ! $this->success;
    }
}
