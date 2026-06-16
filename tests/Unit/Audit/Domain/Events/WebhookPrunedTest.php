<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Audit\Domain\Events\WebhookPruned;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

it('represents pruned webhook logs', function () {
    $event = new WebhookPruned(
        deletedCount: 42,
        cutoff: '2026-06-15T10:00:00+00:00',
        dryRun: false,
    );

    expect($event)->toBeInstanceOf(DomainEvent::class)
        ->and($event->deletedCount)->toBe(42)
        ->and($event->cutoff)->toBe('2026-06-15T10:00:00+00:00')
        ->and($event->dryRun)->toBeFalse()
        ->and($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});
