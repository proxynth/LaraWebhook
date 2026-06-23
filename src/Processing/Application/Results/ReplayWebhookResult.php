<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Results;

use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogSummary;
use Proxynth\Larawebhook\Shared\Application\Results\Result;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

final readonly class ReplayWebhookResult implements Result
{
    public function __construct(
        public WebhookLogSummary $log,
        public ?string $errorMessage = null,
        /** @var list<DomainEvent> */
        public array $events = [],
    ) {}

    public static function fromSummary(
        WebhookLogSummary $log,
        ?string $errorMessage = null,
        array $events = [],
    ): self {
        return new self($log, $errorMessage, $events);
    }
}
