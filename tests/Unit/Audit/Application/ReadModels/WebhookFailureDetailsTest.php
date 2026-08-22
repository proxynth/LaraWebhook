<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookFailureDetails;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\WebhookLogReadModelFactory;

it('can be created from failed webhook log model', function () {
    $log = WebhookLog::factory()->create([
        'service' => 'github',
        'event' => 'push',
        'status' => 'failed',
        'error_message' => 'Invalid GitHub webhook signature.',
        'attempt' => 1,
        'external_id' => 'delivery_123',
        'idempotency_key' => 'delivery_123',
    ]);

    $readModel = (new WebhookLogReadModelFactory)->failureDetails($log);

    expect($readModel)->toBeInstanceOf(WebhookFailureDetails::class)
        ->and($readModel->id)->toBe($log->id)
        ->and($readModel->service)->toBe('github')
        ->and($readModel->status)->toBe('failed')
        ->and($readModel->errorMessage)->toBe('Invalid GitHub webhook signature.')
        ->and($readModel->attempt)->toBe(1)
        ->and($readModel->idempotencyKey)->toBe('delivery_123');
});
