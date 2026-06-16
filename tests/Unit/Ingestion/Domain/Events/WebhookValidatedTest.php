<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Ingestion\Domain\Events\WebhookValidated;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

it('represents a validated webhook', function () {
    $event = new WebhookValidated(
        provider: 'github',
        event: 'push',
        externalId: 'delivery_123',
    );

    expect($event)->toBeInstanceOf(DomainEvent::class)
        ->and($event->provider)->toBe('github')
        ->and($event->event)->toBe('push')
        ->and($event->externalId)->toBe('delivery_123')
        ->and($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});
