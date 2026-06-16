<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Domain\Events;

use DateTimeImmutable;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

final readonly class WebhookProcessingFailed implements DomainEvent
{
    public function __construct(
        public string $provider,
        public ?string $event,
        public ?string $externalId,
        public int $attempt,
        public string $reason,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
