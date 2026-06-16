<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Processing\Domain\Events\WebhookProcessingFailed;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

it('represents a webhook processing failure', function () {
    $event = new WebhookProcessingFailed(
        provider: 'stripe',
        event: 'invoice.paid',
        externalId: 'evt_123',
        attempt: 1,
        reason: 'validation_failed',
    );

    expect($event)->toBeInstanceOf(DomainEvent::class)
        ->and($event->provider)->toBe('stripe')
        ->and($event->event)->toBe('invoice.paid')
        ->and($event->externalId)->toBe('evt_123')
        ->and($event->attempt)->toBe(1)
        ->and($event->reason)->toBe('validation_failed')
        ->and($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});
