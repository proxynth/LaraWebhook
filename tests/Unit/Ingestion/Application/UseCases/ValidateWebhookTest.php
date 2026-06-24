<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Ingestion\Application\Commands\ValidateWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Ports\SignatureValidator;
use Proxynth\Larawebhook\Ingestion\Application\UseCases\ValidateWebhook;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

beforeEach(function () {
    app()->forgetInstance(SignatureValidator::class);
});

it('returns a valid result for a valid github webhook', function () {
    $fakeValidator = new class implements SignatureValidator
    {
        public array $calls = [];

        public function validate(
            WebhookService $service,
            RawPayload $payload,
            Signature $signature,
            string $secret,
        ): bool {
            $this->calls[] = compact('service', 'payload', 'signature', 'secret');

            return true;
        }
    };

    app()->instance(SignatureValidator::class, $fakeValidator);

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

    expect($fakeValidator->calls)->toHaveCount(1)
        ->and($fakeValidator->calls[0]['service'])->toBe(WebhookService::Github)
        ->and($fakeValidator->calls[0]['payload'])->toBe($payload)
        ->and($fakeValidator->calls[0]['secret'])->toBe($secret);
});

it('returns an invalid result for an invalid github signature', function () {
    $fakeValidator = new class implements SignatureValidator
    {
        public function validate(
            WebhookService $service,
            RawPayload $payload,
            Signature $signature,
            string $secret,
        ): bool {
            return false;
        }
    };

    app()->instance(SignatureValidator::class, $fakeValidator);

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
        ->and($result->errorMessage)->toBe('Invalid webhook signature.');
});

it('uses the explicit command secret instead of service config', function () {
    $fakeValidator = new class implements SignatureValidator
    {
        public ?string $capturedSecret = null;

        public function validate(
            WebhookService $service,
            RawPayload $payload,
            Signature $signature,
            string $secret,
        ): bool {
            $this->capturedSecret = $secret;

            return true;
        }
    };

    app()->instance(SignatureValidator::class, $fakeValidator);

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

    expect($result->isValid())->toBeTrue()
        ->and($fakeValidator->capturedSecret)->toBe($secret);
});
