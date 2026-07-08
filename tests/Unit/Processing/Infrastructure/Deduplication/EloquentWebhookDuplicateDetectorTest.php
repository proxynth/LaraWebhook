<?php

use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Processing\Infrastructure\Deduplication\EloquentWebhookDuplicateDetector;
use Proxynth\Larawebhook\Processing\Infrastructure\Persistence\Models\ProcessedWebhookEvent;

it('detects already processed webhook using processed events table', function () {
    ProcessedWebhookEvent::query()->create([
        'service' => 'github',
        'idempotency_key' => 'delivery_123',
        'external_id' => 'delivery_123',
        'event' => 'push',
        'processed_at' => now(),
    ]);

    $detector = app(EloquentWebhookDuplicateDetector::class);

    expect($detector->alreadyProcessed('github', 'delivery_123'))->toBeTrue();
});

it('does not use webhook logs for duplicate detection anymore', function () {
    WebhookLog::factory()->create([
        'service' => 'github',
        'external_id' => 'delivery_123',
        'idempotency_key' => 'delivery_123',
        'status' => 'success',
    ]);

    $detector = app(EloquentWebhookDuplicateDetector::class);

    expect($detector->alreadyProcessed('github', 'delivery_123'))->toBeFalse();
});
