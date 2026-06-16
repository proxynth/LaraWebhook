<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Shared\Domain\Events;

use DateTimeImmutable;

interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
