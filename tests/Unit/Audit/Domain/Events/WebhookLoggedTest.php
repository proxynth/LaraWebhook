<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Audit\Domain\Events\WebhookLogged;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

it('represents a logged webhook without exposing payload', function () {
    $event = new WebhookLogged(
        webhookLogId: 123,
        provider: 'stripe',
        event: 'invoice.paid',
        status: 'success',
    );

    expect($event)->toBeInstanceOf(DomainEvent::class)
        ->and($event->webhookLogId)->toBe(123)
        ->and($event->provider)->toBe('stripe')
        ->and($event->event)->toBe('invoice.paid')
        ->and($event->status)->toBe('success')
        ->and($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});
