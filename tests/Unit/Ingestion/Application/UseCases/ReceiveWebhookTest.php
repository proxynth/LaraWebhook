<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Proxynth\Larawebhook\Audit\Application\Data\WebhookLogData;
use Proxynth\Larawebhook\Audit\Domain\Events\WebhookLogged;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ReceiveWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookSecretResolver;
use Proxynth\Larawebhook\Ingestion\Application\Results\ReceiveWebhookResult;
use Proxynth\Larawebhook\Ingestion\Application\UseCases\ReceiveWebhook;
use Proxynth\Larawebhook\Ingestion\Domain\Events\WebhookReceived;
use Proxynth\Larawebhook\Ingestion\Domain\Events\WebhookRejected;
use Proxynth\Larawebhook\Ingestion\Domain\Events\WebhookValidated;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Processing\Application\Data\RetryConfiguration;
use Proxynth\Larawebhook\Processing\Application\Ports\ProcessedWebhookRecorder;
use Proxynth\Larawebhook\Processing\Application\Ports\RetryConfigurationResolver;
use Proxynth\Larawebhook\Processing\Application\Ports\WebhookDuplicateDetector;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;
use Proxynth\Larawebhook\Tests\Fakes\Ingestion\FakeWebhookSecretResolver;
use Proxynth\Larawebhook\Tests\Fakes\Processing\FakeProcessedWebhookRecorder;
use Proxynth\Larawebhook\Tests\Fakes\Processing\FakeRetryConfigurationResolver;
use Proxynth\Larawebhook\Tests\Fakes\Processing\FakeWebhookDuplicateDetector;

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

beforeEach(function () {
    Queue::fake();

    $this->duplicateDetector = new FakeWebhookDuplicateDetector;
    app()->instance(WebhookDuplicateDetector::class, $this->duplicateDetector);

    config()->set('larawebhook.services.github.webhook_secret', 'secret');
    config()->set('larawebhook.retries.enabled', true);
    app()->instance(
        RetryConfigurationResolver::class,
        new FakeRetryConfigurationResolver(new RetryConfiguration(
            enabled: true,
            async: false,
        )),
    );
});

it('receives a valid webhook and logs it successfully', function () {
    app()->instance(WebhookSecretResolver::class, new FakeWebhookSecretResolver([
        'github' => 'github_secret',
    ]));

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
        ->and($result->log)->toBeInstanceOf(WebhookLogData::class)
        ->and($result->log->service)->toBe('github')
        ->and($result->log->status)->toBe('success')
        ->and($result->log->externalId)->toBe('delivery_123')
        ->and($result->idempotencyKey)->toBe('delivery_123')
        ->and($result->events)->toHaveCount(3)
        ->and($result->events[0])->toBeInstanceOf(WebhookReceived::class)
        ->and($result->events[1])->toBeInstanceOf(WebhookValidated::class)
        ->and($result->events[2])->toBeInstanceOf(WebhookLogged::class)
        ->and(WebhookLog::query()->count())->toBe(1);
});

it('returns already processed when the idempotency key already exists', function () {
    app()->instance(WebhookSecretResolver::class, new FakeWebhookSecretResolver([
        'github' => 'github_secret',
    ]));

    $payload = githubPayload();
    $signature = githubSignature($payload);
    $this->duplicateDetector->shouldAlreadyProcessed('github', 'delivery_123');

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: $signature,
        externalIdHeaderValue: 'delivery_123'
    ));

    expect($result->isAlreadyProcessed())->toBeTrue()
        ->and($result->externalId)->toBe('delivery_123')
        ->and($result->idempotencyKey)->toBe('delivery_123')
        ->and($result->log)->toBeNull()
        ->and(WebhookLog::query()->count())->toBe(0);
});

it('uses payload hash idempotency fallback when no external id is available', function () {
    app()->instance(WebhookSecretResolver::class, new FakeWebhookSecretResolver([
        'github' => 'github_secret',
    ]));

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
        ->and($result->externalId)->toBeNull()
        ->and($result->log?->externalId)->toBeNull()
        ->and($result->idempotencyKey)->toStartWith('payload_hash:');
});

it('returns secret not configured when webhook secret is missing', function () {
    config()->set('larawebhook.services.github.webhook_secret', null);

    app()->instance(WebhookSecretResolver::class, new FakeWebhookSecretResolver([
        'github' => null,
    ]));

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
    app()->instance(WebhookSecretResolver::class, new FakeWebhookSecretResolver([
        'github' => 'github_secret',
    ]));

    $payload = githubPayload();

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: incomingSignature('sha256=invalid'),
        externalIdHeaderValue: 'delivery_invalid',
    ));

    expect($result->isFailed())->toBeTrue()
        ->and($result->log)->toBeInstanceOf(WebhookLogData::class)
        ->and($result->log?->status)->toBe('failed')
        ->and($result->failureStatusCode)->toBeInt()
        ->and($result->errorMessage)->not->toBeNull()
        ->and($result->events)->toHaveCount(3)
        ->and($result->events[0])->toBeInstanceOf(WebhookReceived::class)
        ->and($result->events[1])->toBeInstanceOf(WebhookRejected::class)
        ->and($result->events[2])->toBeInstanceOf(WebhookLogged::class)
        ->and(WebhookLog::query()->count())->toBe(1);
});

it('returns accepted for retry when signature is invalid and async retires are enabled', function () {
    app()->instance(
        RetryConfigurationResolver::class,
        new FakeRetryConfigurationResolver(new RetryConfiguration(
            enabled: true,
            async: true,
        )),
    );

    app()->instance(WebhookSecretResolver::class, new FakeWebhookSecretResolver([
        'github' => 'github_secret',
    ]));

    $payload = githubPayload();

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: incomingSignature('sha256=invalid'),
        externalIdHeaderValue: 'delivery_retry'
    ));

    expect($result->isAcceptedForRetry())->toBeTrue()
        ->and($result->log)->toBeInstanceOf(WebhookLogData::class)
        ->and($result->log?->status)->toBe('failed')
        ->and($result->event)->not->toBeNull()
        ->and($result->idempotencyKey)->toBe('delivery_retry');
});

it('handles invalid json payload without crashing', function () {
    app()->instance(WebhookSecretResolver::class, new FakeWebhookSecretResolver([
        'github' => 'github_secret',
    ]));

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

it('records the idempotency key separately from provider external id', function () {
    app()->instance(WebhookSecretResolver::class, new FakeWebhookSecretResolver([
        'github' => 'github_secret',
    ]));

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
        ->and($result->log?->externalId)->toBeNull()
        ->and($result->log?->idempotencyKey)->toBe($result->idempotencyKey);
});

it('processes valid webhook through domain event lifecycle', function () {
    app()->instance(WebhookSecretResolver::class, new FakeWebhookSecretResolver([
        'github' => 'github_secret',
    ]));

    $payload = githubPayload();
    $signature = githubSignature($payload);

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: $signature,
        externalIdHeaderValue: 'delivery_123',
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->log)->toBeInstanceOf(WebhookLogData::class)
        ->and($result->log?->status)->toBe('success')
        ->and($result->log?->externalId)->toBe('delivery_123')
        ->and($result->idempotencyKey)->toBe('delivery_123');
});

it('marks webhook as failed when validation fails and async retries are disabled', function () {
    app()->instance(WebhookSecretResolver::class, new FakeWebhookSecretResolver([
        'github' => 'github_secret',
    ]));

    $payload = githubPayload();

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: Signature::fromString('sha256=invalid'),
        externalIdHeaderValue: 'delivery_invalid',
    ));

    expect($result->isFailed())->toBeTrue()
        ->and($result->log)->toBeInstanceOf(WebhookLogData::class)
        ->and($result->log?->status)->toBe('failed')
        ->and($result->errorMessage)->not->toBeNull();
});

it('returns secret not configured when resolver returns null', function () {
    app()->instance(WebhookSecretResolver::class, new FakeWebhookSecretResolver([
        'github' => null,
    ]));

    $payload = githubPayload();

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: Signature::fromString('sha256=invalid'),
        externalIdHeaderValue: 'delivery_123',
    ));

    expect($result->status)->toBe('secret_not_configured');
});

it('records processed webhook event after succesful receive', function () {
    $processedRecorder = new FakeProcessedWebhookRecorder;

    app()->instance(ProcessedWebhookRecorder::class, $processedRecorder);

    app()->instance(WebhookSecretResolver::class, new FakeWebhookSecretResolver([
        'github' => 'github_secret',
    ]));

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
        externalIdHeaderValue: 'delivery_123',
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($processedRecorder->records)->toHaveCount(1)
        ->and($processedRecorder->records[0])->toMatchArray([
            'service' => 'github',
            'idempotencyKey' => 'delivery_123',
            'externalId' => 'delivery_123',
        ]);
});

it('does not record processed webhook event when validation fails', function () {
    $processedRecorder = new FakeProcessedWebhookRecorder;

    app()->instance(ProcessedWebhookRecorder::class, $processedRecorder);

    $result = app(ReceiveWebhook::class)->handle(new ReceiveWebhookCommand(
        service: WebhookService::Github,
        payload: githubPayload(),
        signature: Signature::fromString('sha256=invalid'),
        externalIdHeaderValue: 'delivery_123',
    ));

    expect($result->isSuccess())->toBeFalse()
        ->and($processedRecorder->records)->toBeEmpty();
});

it('returns already processed when processed event was recorded concurrently', function () {
    $processedRecorder = new FakeProcessedWebhookRecorder(
        alreadyRecorded: true,
    );

    app()->instance(ProcessedWebhookRecorder::class, $processedRecorder);

    app()->instance(WebhookSecretResolver::class, new FakeWebhookSecretResolver([
        'github' => 'github_secret',
    ]));

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
        externalIdHeaderValue: 'delivery_123',
    ));

    expect($result->isAlreadyProcessed())->toBeTrue()
        ->and($result->externalId)->toBe('delivery_123')
        ->and($result->idempotencyKey)->toBe('delivery_123');
});
