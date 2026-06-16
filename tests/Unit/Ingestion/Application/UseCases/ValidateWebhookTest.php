<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ValidateWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\UseCases\ValidateWebhook;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;

it('returns a valid result for a valid github webhook', function () {
    $secret = 'github_secret';
    $payload = RawPayload::fromString('{"action":"opened"}');
    $signature = incomingSignature('sha256='.hash_hmac('sha256', $payload->value(), $secret));

    $result = app(ValidateWebhook::class)->handle(new ValidateWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: $signature,
        event: 'pull_request.opened',
        externalId: 'delivery_123',
        secret: $secret,
    ));

    expect($result->isValid())->toBeTrue()
        ->and($result->service)->toBe('github')
        ->and($result->event)->toBe('pull_request.opened')
        ->and($result->externalId)->toBe('delivery_123')
        ->and($result->payload)->toBe(['action' => 'opened'])
        ->and($result->errorMessage)->toBeNull();
});

it('returns an invalid result for an invalid github signature', function () {
    $payload = RawPayload::fromString('{"action":"opened"}');

    $result = app(ValidateWebhook::class)->handle(new ValidateWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: incomingSignature('sha256=invalid'),
        event: 'pull_request.opened',
        externalId: 'delivery_123',
        secret: 'github_secret',
    ));

    expect($result->isInvalid())->toBeTrue()
        ->and($result->service)->toBe('github')
        ->and($result->event)->toBe('pull_request.opened')
        ->and($result->externalId)->toBe('delivery_123')
        ->and($result->errorMessage)->not->toBeNull();
});

it('uses the explicit command secret instead of service config', function () {
    config()->set('larawebhook.services.github.webhook_secret', 'wrong_config_secret');

    $secret = 'command_secret';
    $payload = RawPayload::fromString('{"action":"opened"}');
    $signature = incomingSignature('sha256='.hash_hmac('sha256', $payload->value(), $secret));

    $result = app(ValidateWebhook::class)->handle(new ValidateWebhookCommand(
        service: WebhookService::Github,
        payload: $payload,
        signature: $signature,
        event: 'pull_request.opened',
        externalId: 'delivery_123',
        secret: $secret,
    ));

    expect($result->isValid())->toBeTrue();
});
