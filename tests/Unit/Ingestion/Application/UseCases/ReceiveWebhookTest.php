<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ReceiveWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Results\ReceiveWebhookResult;
use Proxynth\Larawebhook\Ingestion\Application\UseCases\ReceiveWebhook;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;

function githubPayload(string $deliveryId = 'delivery_123'): string
{
    return json_encode([
        'zen' => 'Keep it logically awesome.',
        'hook_id' => 123,
        'repository' => [
            'id' => 456,
            'full_name' => 'proxynth/larawebhook',
        ],
    ], JSON_THROW_ON_ERROR);
}

function githubSignature(string $payload, string $secret = 'secret'): Signature
{
    return incomingSignature(
        'sha256='.hash_hmac('sha256', $payload, $secret)
    );
}

beforeEach(function () {
    Queue::fake();

    config()->set('larawebhook.services.github.webhook_secret', 'secret');
    config()->set('larawebhook.retries.enabled', true);
    config()->set('larawebhook.retries.async', false);
});

it('receives a valid webhook and logs it successfully', function () {
    $payload = githubPayload();
    $signature = githubSignature($payload);

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: $signature,
        externalIdHeaderValue: 'delivery_123'
    ));

    expect($result)->toBeInstanceOf(ReceiveWebhookResult::class)
        ->and($result->isSuccess())->toBeTrue()
        ->and($result->log)->toBeInstanceOf(WebhookLog::class)
        ->and($result->log->service)->toBe('github')
        ->and($result->log->status)->toBe('success')
        ->and($result->log->external_id)->toBe('delivery_123')
        ->and($result->idempotencyKey)->toBe('delivery_123')
        ->and(WebhookLog::query()->count())->toBe(1);
});

it('returns already processed when the idempotency key already exists', function () {
    WebhookLog::factory()->create([
        'service' => 'github',
        'external_id' => 'delivery_123',
        'status' => 'success',
    ]);

    $payload = githubPayload();
    $signature = githubSignature($payload);

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: $signature,
        externalIdHeaderValue: 'delivery_123'
    ));

    expect($result->isAlreadyProcessed())->toBeTrue()
        ->and($result->externalId)->toBe('delivery_123')
        ->and($result->idempotencyKey)->toBe('delivery_123')
        ->and(WebhookLog::query()->count())->toBe(1);
});

it('uses payload hash idempotency fallback when no external id is available', function () {
    $payload = json_encode([
        'type' => 'custom.event',
        'data' => [
            'foo' => 'bar',
        ],
    ], JSON_THROW_ON_ERROR);

    $signature = githubSignature($payload);

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: $signature,
        externalIdHeaderValue: null,
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->log?->external_id)->toStartWith('payload_hash:')
        ->and($result->idempotencyKey)->toStartWith('payload_hash:');
});

it('returns secret not configured when webhook secret is missing', function () {
    config()->set('larawebhook.services.github.webhook_secret', null);

    $payload = githubPayload();

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: incomingSignature('sha256=anything'),
        externalIdHeaderValue: 'delivery_123'
    ));

    expect($result->isSecretNotConfigured())->toBeTrue()
        ->and($result->errorMessage)->toContain('Webhook secret not configured')
        ->and($result->log)->toBeNull()
        ->and(WebhookLog::query()->count())->toBe(0);
});

it('returns failed when signature is invalid and async retries are disabled', function () {
    config()->set('larawebhook.retries.enabled', true);
    config()->set('larawebhook.retries.async', false);

    $payload = githubPayload();

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: incomingSignature('sha256=invalid'),
        externalIdHeaderValue: 'delivery_invalid',
    ));

    expect($result->isFailed())->toBeTrue()
        ->and($result->log)->toBeInstanceOf(WebhookLog::class)
        ->and($result->log?->status)->toBe('failed')
        ->and($result->failureStatusCode)->toBeInt()
        ->and($result->errorMessage)->not->toBeNull()
        ->and(WebhookLog::query()->count())->toBe(1);
});

it('returns accepted for retry when signature is invalid and async retires are enabled', function () {
    config()->set('larawebhook.retries.enabled', true);
    config()->set('larawebhook.retries.async', true);

    $payload = githubPayload();

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: incomingSignature('sha256=invalid'),
        externalIdHeaderValue: 'delivery_retry'
    ));

    expect($result->isAcceptedForRetry())->toBeTrue()
        ->and($result->log)->toBeInstanceOf(WebhookLog::class)
        ->and($result->log?->status)->toBe('failed')
        ->and($result->event)->not->toBeNull()
        ->and($result->secret)->toBe('secret')
        ->and($result->idempotencyKey)->toBe('delivery_retry');
});

it('handles invalid json payload without crashing', function () {
    $payload = '{invalid-json';
    $signature = githubSignature($payload);

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: $signature,
        externalIdHeaderValue: 'delivery_invalid_json',
    ));

    expect($result->isSuccess() || $result->isFailed() || $result->isAcceptedForRetry())->toBeTrue()
        ->and($result->idempotencyKey)->toBe('delivery_invalid_json');
});

it('records the idempotency key as log external id while keeping provider external id null', function () {
    $payload = json_encode([
        'type' => 'custom.event',
        'data' => ['foo' => 'bar'],
    ], JSON_THROW_ON_ERROR);

    $signature = githubSignature($payload);

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: $signature,
        externalIdHeaderValue: null,
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->externalId)->toBeNull()
        ->and($result->idempotencyKey)->toStartWith('payload_hash:')
        ->and($result->log?->external_id)->toBe($result->idempotencyKey);
});
