<?php

use Proxynth\Larawebhook\Services\PayloadStorageResolver;

beforeEach(function () {
    $this->payloadStorageResolver = app()->make(PayloadStorageResolver::class);
});

it('does not store payload in none mode', function () {
    config()->set('larawebhook.payload_storage.mode', 'none');

    $payload = ['email' => 'client@example.com'];

    expect($this->payloadStorageResolver->resolve($payload))->toBeNull();
});

it('stores full payload in full mode', function () {
    config()->set('larawebhook.payload_storage.mode', 'full');

    $payload = ['email' => 'client@example.com'];

    expect($this->payloadStorageResolver->resolve($payload))->toBe($payload);
});

it('stores redacted payload in redacted mode', function () {
    config()->set('larawebhook.payload_storage.mode', 'redacted');
    config()->set('larawebhook.redaction.fields', ['email', 'client_secret']);
    config()->set('larawebhook.redaction.replacement', '[REDACTED]');

    $payload = [
        'email' => 'client@example.com',
        'event' => 'invoice.paid',
        'payment' => [
            'client_secret' => 'pi_secret_123',
            'amount' => 4900,
        ],
    ];

    $resolved = app(PayloadStorageResolver::class)->resolve($payload);

    expect($resolved)->toBe([
        'email' => '[REDACTED]',
        'event' => 'invoice.paid',
        'payment' => [
            'client_secret' => '[REDACTED]',
            'amount' => 4900,
        ],
    ]);
});
