<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Proxynth\Larawebhook\Processing\Infrastructure\Persistence\EloquentProcessedWebhookRecorder;
use Proxynth\Larawebhook\Processing\Infrastructure\Persistence\Models\ProcessedWebhookEvent;

it('records processed webhook event', function () {
    app(EloquentProcessedWebhookRecorder::class)->recordProcessed(
        service: 'github',
        idempotencyKey: 'delivery_123',
        externalId: 'delivery_123',
        event: 'push',
    );

    expect(ProcessedWebhookEvent::query()->count())->toBe(1);

    $processed = ProcessedWebhookEvent::query()->first();

    expect($processed->service)->toBe('github')
        ->and($processed->idempotency_key)->toBe('delivery_123')
        ->and($processed->external_id)->toBe('delivery_123')
        ->and($processed->event)->toBe('push')
        ->and($processed->processed_at)->not->toBeNull();
});

it('enforces unique constraint on processed webhook events service and idempotency key', function () {
    ProcessedWebhookEvent::query()->create([
        'service' => 'stripe',
        'idempotency_key' => 'idem_unique',
        'external_id' => 'evt_unique',
        'event' => 'event1',
        'processed_at' => now(),
    ]);

    expect(fn () => ProcessedWebhookEvent::query()->create([
        'service' => 'stripe',
        'idempotency_key' => 'idem_unique',
        'external_id' => 'evt_other',
        'event' => 'event2',
        'processed_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('returns already recorded when processed webhook already exists', function () {
    $recorder = app(EloquentProcessedWebhookRecorder::class);

    $first = $recorder->recordProcessed(
        service: 'github',
        idempotencyKey: 'delivery_123',
        externalId: 'delivery_123',
        event: 'push',
    );

    $second = $recorder->recordProcessed(
        service: 'github',
        idempotencyKey: 'delivery_123',
        externalId: 'delivery_123',
        event: 'push',
    );

    expect($first->recorded)->toBeTrue()
        ->and($first->alreadyRecorded)->toBeFalse()
        ->and($second->recorded)->toBeFalse()
        ->and($second->alreadyRecorded)->toBeTrue()
        ->and(ProcessedWebhookEvent::query()->count())->toBe(1);
});
