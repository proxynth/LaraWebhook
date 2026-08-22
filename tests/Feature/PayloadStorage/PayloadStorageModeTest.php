<?php

use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\Data\WebhookLogData;
use Proxynth\Larawebhook\Audit\Application\UseCases\RecordWebhookLog;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\DeliveryAttempt;

it('does not store payload when payload storage mode is none', function () {
    config()->set('larawebhook.payload_storage.mode', 'none');

    $payload = [
        'email' => 'client@example.com',
        'event' => 'invoice.paid',
    ];

    $log = app(RecordWebhookLog::class)->handle(new RecordWebhookLogCommand(
        service: 'stripe',
        event: 'invoice.paid',
        valid: true,
        payload: $payload,
        attempt: DeliveryAttempt::initial()->value(),
        externalId: 'evt_test_123',
    ));

    expect($log)->toBeInstanceOf(WebhookLogData::class)
        ->and($log->status)->toBe('success')
        ->and($log->payload)->toBeNull();
});

it('stores full payload when payload storage mode is full', function () {
    config()->set('larawebhook.payload_storage.mode', 'full');

    $payload = [
        'email' => 'client@example.com',
        'event' => 'invoice.paid',
    ];

    $log = app(RecordWebhookLog::class)->handle(new RecordWebhookLogCommand(
        service: 'stripe',
        event: 'invoice.paid',
        valid: true,
        payload: $payload,
        attempt: DeliveryAttempt::initial()->value(),
        externalId: 'evt_test_123',
    ));

    expect($log->payload)->toBe($payload);
});

it('stores redacted payload when payload storage mode is redacted', function () {
    config()->set('larawebhook.payload_storage.mode', 'redacted');
    config()->set('larawebhook.redaction.fields', ['email']);
    config()->set('larawebhook.redaction.replacement', '[REDACTED]');

    $payload = [
        'email' => 'client@example.com',
        'event' => 'invoice.paid',
        'amount' => 1000,
    ];

    $log = app(RecordWebhookLog::class)->handle(new RecordWebhookLogCommand(
        service: 'stripe',
        event: 'invoice.paid',
        valid: true,
        payload: $payload,
        attempt: DeliveryAttempt::initial()->value(),
        externalId: 'evt_test_123',
    ));

    expect($log->payload)->toBeArray()
        ->and($log->payload['email'])->toBe('[REDACTED]')
        ->and($log->payload['event'])->toBe('invoice.paid')
        ->and($log->payload['amount'])->toBe(1000);
});

it('applies payload storage mode when recording failed webhook logs', function () {
    config()->set('larawebhook.payload_storage.mode', 'none');

    $payload = [
        'email' => 'client@example.com',
        'event' => 'invoice.paid',
    ];

    $log = app(RecordWebhookLog::class)->handle(new RecordWebhookLogCommand(
        service: 'stripe',
        event: 'invoice.paid',
        valid: false,
        payload: $payload,
        attempt: DeliveryAttempt::initial()->value(),
        externalId: 'evt_test_123',
        errorMessage: 'Invalid signature.',
    ));

    expect($log->status)->toBe('failed')
        ->and($log->errorMessage)->toBe('Invalid signature.')
        ->and($log->payload)->toBeNull();
});
