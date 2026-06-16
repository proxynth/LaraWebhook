<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogSummary;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

it('can be created from webhook log model', function () {
    $log = WebhookLog::factory()->create([
        'service' => 'github',
        'event' => 'push',
        'status' => 'success',
        'attempt' => 0,
        'external_id' => 'delivery_123',
    ]);

    $readModel = WebhookLogSummary::fromModel($log);

    expect($readModel)->toBeInstanceOf(WebhookLogSummary::class)
        ->and($readModel->id)->toBe($log->id)
        ->and($readModel->service)->toBe('github')
        ->and($readModel->event)->toBe('push')
        ->and($readModel->status)->toBe('success')
        ->and($readModel->attempt)->toBe(0)
        ->and($readModel->externalId)->toBe('delivery_123')
        ->and($readModel->createdAt)->toBe($log->created_at->toISOString());
});
