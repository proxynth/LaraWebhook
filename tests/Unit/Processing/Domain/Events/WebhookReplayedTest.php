<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Processing\Domain\Events\WebhookReplayed;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

it('represents a replayed webhook', function () {
    $event = new WebhookReplayed(
        webhookLogId: 123,
        provider: 'stripe',
        event: 'invoice.paid',
        attempt: 2,
    );

    expect($event)->toBeInstanceOf(DomainEvent::class)
        ->and($event->webhookLogId)->toBe(123)
        ->and($event->provider)->toBe('stripe')
        ->and($event->event)->toBe('invoice.paid')
        ->and($event->attempt)->toBe(2)
        ->and($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});
