<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Domain\Events;

use DateTimeImmutable;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

final readonly class WebhookLogged implements DomainEvent
{
    public function __construct(
        public int|string $webhookLogId,
        public string $provider,
        public ?string $event,
        public string $status,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
