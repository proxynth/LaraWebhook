<?php

use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Ingestion\Infrastructure\Validation\WebhookValidator;

beforeEach(function () {
    $this->secret = 'test_secret_key_123';
    $this->validator = new WebhookValidator($this->secret, 300);
});

it('does not store payload when payload storage mode is none', function () {
    config()->set('larawebhook.payload_storage.mode', 'none');

    $payload = [
        'email' => 'client@example.com',
        'event' => 'invoice.paid',
    ];

    $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = incomingSignature($encodedPayload);

    $log = $this->validator->validateAndLog(
        $encodedPayload,
        $signature,
        'stripe',
        'payment_intent.succeeded'
    );

    expect($log)->toBeInstanceOf(WebhookLog::class)
        ->and($log->payload)->toBeNull();
});

it('stores full payload when payload storage mode is full', function () {
    config()->set('larawebhook.payload_storage.mode', 'full');

    $payload = [
        'email' => 'client@example.com',
        'event' => 'invoice.paid',
    ];

    $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = incomingSignature($encodedPayload);

    $log = $this->validator->validateAndLog(
        $encodedPayload,
        $signature,
        'stripe',
        'payment_intent.succeeded'
    );

    expect($log->payload)->toBe($payload);
});

it('stores partial/redacted payload when payload storage mode is redacted', function () {
    config()->set('larawebhook.payload_storage.mode', 'redacted');

    $payload = [
        'email' => 'client@example.com',
        'event' => 'invoice.paid',
    ];

    $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = incomingSignature($encodedPayload);

    $log = $this->validator->validateAndLog(
        $encodedPayload,
        $signature,
        'stripe',
        'payment_intent.succeeded'
    );

    expect($log->payload)->toBe([
        'email' => '[REDACTED]',
        'event' => 'invoice.paid',
    ]);
});
