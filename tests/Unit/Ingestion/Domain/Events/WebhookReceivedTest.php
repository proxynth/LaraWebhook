<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Ingestion\Domain\Events\WebhookReceived;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

it('represents a received webhook without exposing payload', function () {
    $event = new WebhookReceived(
        provider: 'stripe',
        event: 'invoice.paid',
        externalId: 'evt_123',
    );

    expect($event)->toBeInstanceOf(DomainEvent::class)
        ->and($event->provider)->toBe('stripe')
        ->and($event->event)->toBe('invoice.paid')
        ->and($event->externalId)->toBe('evt_123')
        ->and($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});
