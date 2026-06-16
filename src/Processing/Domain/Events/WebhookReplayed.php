<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Domain\Events;

use DateTimeImmutable;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

final readonly class WebhookReplayed implements DomainEvent
{
    public function __construct(
        public int|string $webhookLogId,
        public string $provider,
        public ?string $event,
        public int $attempt,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
