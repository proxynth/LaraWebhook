<?php

use Proxynth\Larawebhook\Audit\Infrastructure\Payload\PayloadRedactor;

beforeEach(function () {
    $this->payloadRedactor = new PayloadRedactor;
});

it('redacts sensitive root fields', function () {
    config()->set('larawebhook.redaction.fields', ['email']);
    config()->set('larawebhook.redaction.replacement', '[REDACTED]');

    $payload = [
        'email' => 'client@example.com',
        'event' => 'invoice.paid',
    ];

    $redacted = $this->payloadRedactor->redact($payload);

    expect($redacted)->toBe([
        'email' => '[REDACTED]',
        'event' => 'invoice.paid',
    ]);
});

it('redacts sensitive nested fields recursively', function () {
    config()->set('larawebhook.redaction.fields', ['email', 'phone']);

    $payload = [
        'customer' => [
            'email' => 'client.example.com',
            'phone' => '+33612345678',
            'name' => 'John Doe',
        ],
    ];

    $redacted = $this->payloadRedactor->redact($payload);

    expect($redacted['customer'])->toBe([
        'email' => '[REDACTED]',
        'phone' => '[REDACTED]',
        'name' => 'John Doe',
    ]);
});

it('redacts sensitive fields inside lists', function () {
    config()->set('larawebhook.redaction.fields', ['email']);

    $payload = [
        'customers' => [
            ['email' => 'a@example.com', 'name' => 'A'],
            ['email' => 'b@example.com', 'name' => 'B'],
        ],
    ];

    $redacted = $this->payloadRedactor->redact($payload);

    expect($redacted['customers'])->toBe([
        ['email' => '[REDACTED]', 'name' => 'A'],
        ['email' => '[REDACTED]', 'name' => 'B'],

    ]);
});

it('redacts fields case insensitively', function () {
    config()->set('larawebhook.redaction.fields', ['email', 'authorization']);

    $payload = [
        'Email' => 'client@example.com',
        'Authorization' => 'Bearer token',
    ];

    $redacted = $this->payloadRedactor->redact($payload);

    expect($redacted)->toBe([
        'Email' => '[REDACTED]',
        'Authorization' => '[REDACTED]',
    ]);
});

it('keeps non-sensitive fields unchanged', function () {
    config()->set('larawebhook.redaction.fields', ['email']);

    $payload = [
        'event' => 'invoice.paid',
        'amount' => 4900,
        'currency' => 'eur',
    ];

    $redacted = $this->payloadRedactor->redact($payload);

    expect($redacted)->toBe($payload);

});
