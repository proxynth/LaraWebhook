<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Domain\Events;

use DateTimeImmutable;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

final readonly class WebhookPruned implements DomainEvent
{
    public function __construct(
        public int $deletedCount,
        public string $cutoff,
        public bool $dryRun,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
