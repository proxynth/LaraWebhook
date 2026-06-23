<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Processing\Infrastructure\Deduplication\EloquentWebhookDuplicateDetector;

it('detects already processed webhook logs by idempotency key', function () {
    WebhookLog::factory()->create([
        'service' => 'github',
        'idempotency_key' => 'delivery_123',
    ]);

    $detector = app(EloquentWebhookDuplicateDetector::class);

    expect($detector->alreadyProcessed('github', 'delivery_123'))->toBeTrue()
        ->and($detector->alreadyProcessed('github', 'missing'))->toBeFalse()
        ->and($detector->alreadyProcessed('stripe', 'delivery_123'))->toBeFalse();
});
