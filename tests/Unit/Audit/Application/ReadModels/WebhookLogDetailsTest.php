<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogDetails;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

it('can be created from webhook log model', function () {
    $log = WebhookLog::factory()->create([
        'service' => 'stripe',
        'event' => 'invoice.paid',
        'status' => 'failed',
        'payload' => ['invoice' => 'in_123'],
        'error_message' => 'Invalid signature.',
        'attempt' => 2,
        'external_id' => 'evt_123',
    ]);

    $readModel = WebhookLogDetails::fromModel($log);

    expect($readModel)->toBeInstanceOf(WebhookLogDetails::class)
        ->and($readModel->id)->toBe($log->id)
        ->and($readModel->payload)->toBe(['invoice' => 'in_123'])
        ->and($readModel->errorMessage)->toBe('Invalid signature.')
        ->and($readModel->externalId)->toBe('evt_123')
        ->and($readModel->updatedAt)->toBe($log->updated_at->toISOString());
});
