<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Domain\Events;

use DateTimeImmutable;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

final readonly class WebhookRejected implements DomainEvent
{
    public function __construct(
        public string $provider,
        public ?string $event,
        public ?string $externalId,
        public string $reason,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
