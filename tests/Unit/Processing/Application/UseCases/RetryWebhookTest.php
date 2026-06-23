<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Processing\Application\Commands\RetryWebhookCommand;
use Proxynth\Larawebhook\Processing\Application\UseCases\RetryWebhook;

beforeEach(function () {
    config([
        'larawebhook.retries.enabled' => true,
        'larawebhook.retries.max_attempts' => 3,
        'larawebhook.retries.delays' => [1, 5, 10],
    ]);
});

it('records a successful retry and stops retrying', function () {
    $secret = 'github_secret';
    $payload = '{"action":"opened"}';
    $signature = incomingSignature('sha256='.hash_hmac('sha256', $payload, $secret));

    $result = app(RetryWebhook::class)->handle(new RetryWebhookCommand(
        payload: $payload,
        signature: $signature,
        service: WebhookService::Github->value,
        event: 'pull_request.opened',
        secret: $secret,
        attempt: 0,
        externalId: 'delivery_123',
        idempotencyKey: 'dedupe_123',
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->shouldRetry)->toBeFalse()
        ->and($result->nextAttempt)->toBeNull()
        ->and($result->delaySeconds)->toBeNull();

    $log = WebhookLog::latest()->first();

    expect($log)->not->toBeNull()
        ->and($log->service)->toBe('github')
        ->and($log->event)->toBe('pull_request.opened')
        ->and($log->status)->toBe('success')
        ->and($log->attempt)->toBe(0)
        ->and($log->external_id)->toBe('delivery_123')
        ->and($log->idempotency_key)->toBe('dedupe_123')
        ->and($log->error_message)->toBeNull();
});

it('records a failed retry and schedules the next attempt when attempts remain', function () {
    $secret = 'github_secret';
    $payload = '{"action":"opened"}';

    $result = app(RetryWebhook::class)->handle(new RetryWebhookCommand(
        payload: $payload,
        signature: incomingSignature('sha256=invalid'),
        service: WebhookService::Github->value,
        event: 'pull_request.opened',
        secret: $secret,
        attempt: 0,
        externalId: 'delivery_123',
        idempotencyKey: 'dedupe_123',
    ));

    expect($result->isFailed())->toBeTrue()
        ->and($result->shouldRetry)->toBeTrue()
        ->and($result->nextAttempt)->toBe(1)
        ->and($result->delaySeconds)->toBe(1);

    $log = WebhookLog::latest()->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('failed')
        ->and($log->attempt)->toBe(0)
        ->and($log->external_id)->toBe('delivery_123')
        ->and($log->idempotency_key)->toBe('dedupe_123');
});

it('records a failed retry and stops retrying at the max attempt', function () {
    config(['larawebhook.retries.max_attempts' => 1]);

    $result = app(RetryWebhook::class)->handle(new RetryWebhookCommand(
        payload: '{"action":"opened"}',
        signature: incomingSignature('sha256=invalid'),
        service: WebhookService::Github->value,
        event: 'pull_request.opened',
        secret: 'github_secret',
        attempt: 0,
        externalId: 'delivery_123',
        idempotencyKey: 'dedupe_123',
    ));

    expect($result->isFailed())->toBeTrue()
        ->and($result->shouldRetry)->toBeFalse()
        ->and($result->nextAttempt)->toBeNull()
        ->and($result->delaySeconds)->toBeNull();
});

it('throws when the service is unsupported', function () {
    app(RetryWebhook::class)->handle(new RetryWebhookCommand(
        payload: '{"action":"opened"}',
        signature: incomingSignature('sha256=invalid'),
        service: 'unsupported',
        event: 'pull_request.opened',
        secret: 'github_secret',
    ));
})->throws(WebhookException::class, "Webhook service 'unsupported' is not supported.");
