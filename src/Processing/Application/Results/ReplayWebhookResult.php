<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Results;

use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogSummary;
use Proxynth\Larawebhook\Shared\Application\Results\Result;

final readonly class ReplayWebhookResult implements Result
{
    public function __construct(
        public WebhookLogSummary $log,
        public ?string $errorMessage = null,
    ) {}

    public static function fromSummary(WebhookLogSummary $log, ?string $errorMessage = null): self
    {
        return new self($log, $errorMessage);
    }
}
