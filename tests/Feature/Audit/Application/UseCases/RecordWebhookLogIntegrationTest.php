<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\UseCases\RecordWebhookLog;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

it('records a successful webhook log in persistence', function () {
    $log = app(RecordWebhookLog::class)->handle(new RecordWebhookLogCommand(
        service: 'github',
        event: 'push',
        valid: true,
        payload: ['ref' => 'refs/heads/main'],
        attempt: 0,
        externalId: 'delivery_123',
        idempotencyKey: 'delivery_123',
    ));

    expect($log)->toBeInstanceOf(WebhookLog::class)
        ->and(WebhookLog::count())->toBe(1)
        ->and($log->service)->toBe('github')
        ->and($log->status)->toBe('success')
        ->and($log->external_id)->toBe('delivery_123')
        ->and($log->idempotency_key)->toBe('delivery_123');
});

it('records a failed webhook log in persistence', function () {
    $log = app(RecordWebhookLog::class)->handle(new RecordWebhookLogCommand(
        service: 'github',
        event: 'push',
        valid: false,
        payload: ['ref' => 'refs/heads/main'],
        attempt: 1,
        externalId: 'delivery_123',
        idempotencyKey: 'delivery_123',
        errorMessage: 'Invalid GitHub webhook signature.',
    ));

    expect($log)->toBeInstanceOf(WebhookLog::class)
        ->and(WebhookLog::count())->toBe(1)
        ->and($log->status)->toBe('failed')
        ->and($log->attempt)->toBe(1)
        ->and($log->error_message)->toBe('Invalid GitHub webhook signature.');
});
