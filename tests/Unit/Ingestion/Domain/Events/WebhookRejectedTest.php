<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Ingestion\Domain\Events\WebhookRejected;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

it('represents a rejected webhook without exposing sensitive data', function () {
    $event = new WebhookRejected(
        provider: 'slack',
        event: 'url_verification',
        externalId: null,
        reason: 'invalid_signature',
    );

    expect($event)->toBeInstanceOf(DomainEvent::class)
        ->and($event->provider)->toBe('slack')
        ->and($event->event)->toBe('url_verification')
        ->and($event->externalId)->toBeNull()
        ->and($event->reason)->toBe('invalid_signature')
        ->and($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});
