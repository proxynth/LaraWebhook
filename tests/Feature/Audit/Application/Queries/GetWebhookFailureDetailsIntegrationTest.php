<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Audit\Application\Queries\GetWebhookFailureDetails;
use Proxynth\Larawebhook\Audit\Application\Queries\GetWebhookFailureDetailsQuery;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookFailureDetails;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Providers\LarawebhookServiceProvider;

beforeEach(function () {
    app()->register(LarawebhookServiceProvider::class, true);
});

it('gets webhook failure details from eloquent', function () {
    $log = WebhookLog::factory()->create([
        'service' => 'github',
        'event' => 'push',
        'status' => 'failed',
        'error_message' => 'Invalid GitHub webhook signature.',
        'attempt' => 1,
        'external_id' => 'delivery_123',
        'idempotency_key' => 'delivery_123',
    ]);

    $result = app(GetWebhookFailureDetails::class)->handle(
        new GetWebhookFailureDetailsQuery($log->id)
    );

    expect($result)->toBeInstanceOf(WebhookFailureDetails::class)
        ->and($result->id)->toBe($log->id)
        ->and($result->service)->toBe('github')
        ->and($result->status)->toBe('failed')
        ->and($result->idempotencyKey)->toBe('delivery_123');
});
