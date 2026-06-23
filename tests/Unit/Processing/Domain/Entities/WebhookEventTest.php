<?php

use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Provider;
use Proxynth\Larawebhook\Processing\Domain\Entities\WebhookEvent;
use Proxynth\Larawebhook\Processing\Domain\Exceptions\DuplicateWebhookEvent;
use Proxynth\Larawebhook\Processing\Domain\Exceptions\InvalidWebhookState;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\EventType;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\IdempotencyKey;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\WebhookStatus;

function webhookEvent(
    bool $valid = true,
    ?IdempotencyKey $idempotencyKey = null,
    bool $alreadyProcessed = false,
): WebhookEvent {
    return WebhookEvent::received(
        provider: Provider::fromString('github'),
        eventType: EventType::fromString('push'),
        idempotencyKey: $idempotencyKey ?? IdempotencyKey::fromString('delivery_123'),
        valid: $valid,
        alreadyProcessed: $alreadyProcessed,
    );
}

it('can be received', function () {
    $event = webhookEvent();

    expect($event->provider()->value())->toBe('github')
        ->and($event->eventType()->value())->toBe('push')
        ->and($event->status()->isReceived())->toBeTrue()
        ->and($event->isValid())->toBeTrue();
});

it('cannot process invalid event', function () {
    $event = webhookEvent(valid: false);

    $event->markValidated();
})->throws(InvalidWebhookState::class, 'Invalid webhook event cannot be processed.');

it('cannot process already processed event', function () {
    webhookEvent(
        idempotencyKey: IdempotencyKey::fromString('delivery_123'),
        alreadyProcessed: true
    );
})->throws(DuplicateWebhookEvent::class);

it('cannot mutate terminal processed event', function () {
    $event = webhookEvent();

    $event->markValidated();
    $event->markProcessing();
    $event->markProcessed();

    $event->markFailed('late failure');
})->throws(InvalidWebhookState::class);

it('cannot mutate terminal failed event', function () {
    $event = webhookEvent();

    $event->markValidated();
    $event->markProcessing();
    $event->markFailed('handler failed');

    $event->markProcessed();
})->throws(InvalidWebhookState::class);

it('can mark failed from processing', function () {
    $event = webhookEvent();

    $event->markValidated();
    $event->markProcessing();
    $event->markFailed('handler failed');

    expect($event->status()->isFailed())->toBeTrue()
        ->and($event->failureReason())->toBe('handler failed');
});

it('cannot mark processed without idempotency key', function () {
    $event = WebhookEvent::received(
        provider: Provider::fromString('github'),
        eventType: EventType::fromString('push'),
        idempotencyKey: null,
    );

    $event->markValidated();
    $event->markProcessing();
    $event->markProcessed();
})->throws(InvalidWebhookState::class, 'Webhook event without idempotency key cannot be marked as processed.');

it('can replay processed event', function () {
    $event = webhookEvent();

    $event->markValidated();
    $event->markProcessing();
    $event->markProcessed();

    $replayed = $event->replay();

    expect($replayed)->not->toBe($event)
        ->and($replayed->status()->isReplayed())->toBeTrue()
        ->and($replayed->provider()->equals($event->provider()))->toBeTrue()
        ->and($replayed->eventType()->equals($event->eventType()))->toBeTrue();
});

it('can replay failed event', function () {
    $event = webhookEvent();

    $event->markValidated();
    $event->markProcessing();
    $event->markFailed('handler failed');

    $replayed = $event->replay();

    expect($replayed->status()->isReplayed())->toBeTrue();
});

it('can be built in a replayable state before replaying', function () {
    $event = WebhookEvent::replayable(
        provider: Provider::fromString('github'),
        eventType: EventType::fromString('push'),
        idempotencyKey: IdempotencyKey::fromString('delivery_123'),
        status: WebhookStatus::failed(),
    );

    $replayed = $event->replay();

    expect($replayed->status()->isReplayed())->toBeTrue()
        ->and($replayed->provider()->equals($event->provider()))->toBeTrue()
        ->and($replayed->eventType()->equals($event->eventType()))->toBeTrue();
});

it('can replay only when replayable', function () {
    $event = webhookEvent();

    $event->replay();
})->throws(InvalidWebhookState::class);
