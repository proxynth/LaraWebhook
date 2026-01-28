<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Models\WebhookLog;
use Proxynth\Larawebhook\Services\WebhookLogger;

beforeEach(function () {
    $this->logger = new WebhookLogger;
});

describe('WebhookLogger basic logging', function () {
    it('logs a webhook event', function () {
        $log = $this->logger->log(
            service: 'stripe',
            event: 'payment_intent.succeeded',
            status: 'success',
            payload: ['id' => 'pi_123', 'amount' => 1000]
        );

        expect($log)->toBeInstanceOf(WebhookLog::class)
            ->and($log->service)->toBe('stripe')
            ->and($log->event)->toBe('payment_intent.succeeded')
            ->and($log->status)->toBe('success')
            ->and($log->payload)->toBe(['id' => 'pi_123', 'amount' => 1000])
            ->and($log->error_message)->toBeNull();
    });

    it('logs a successful webhook', function () {
        $log = $this->logger->logSuccess(
            service: 'github',
            event: 'push',
            payload: ['ref' => 'refs/heads/main']
        );

        expect($log->service)->toBe('github')
            ->and($log->event)->toBe('push')
            ->and($log->status)->toBe('success')
            ->and($log->error_message)->toBeNull();
    });

    it('logs a failed webhook', function () {
        $log = $this->logger->logFailure(
            service: 'stripe',
            event: 'payment_intent.failed',
            payload: ['id' => 'pi_456'],
            errorMessage: 'Invalid signature'
        );

        expect($log->service)->toBe('stripe')
            ->and($log->event)->toBe('payment_intent.failed')
            ->and($log->status)->toBe('failed')
            ->and($log->error_message)->toBe('Invalid signature');
    });

    it('stores payload as JSON', function () {
        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_123',
                    'amount' => 5000,
                    'currency' => 'usd',
                ],
            ],
        ];

        $log = $this->logger->logSuccess('stripe', 'payment_intent.succeeded', $payload);

        expect($log->payload)->toBeArray()
            ->and($log->payload['type'])->toBe('payment_intent.succeeded')
            ->and($log->payload['data']['object']['amount'])->toBe(5000);
    });
});

describe('WebhookLog model scopes', function () {
    beforeEach(function () {
        // Create test logs
        $this->logger->logSuccess('stripe', 'payment_intent.succeeded', ['id' => '1']);
        $this->logger->logSuccess('stripe', 'payment_intent.succeeded', ['id' => '2']);
        $this->logger->logSuccess('github', 'push', ['ref' => 'main']);
        $this->logger->logFailure('stripe', 'charge.failed', ['id' => '3'], 'Card declined');
        $this->logger->logFailure('github', 'pull_request', ['number' => 1], 'Signature mismatch');
    });

    it('filters logs by service', function () {
        $stripeLogs = WebhookLog::service('stripe')->get();
        $githubLogs = WebhookLog::service('github')->get();

        expect($stripeLogs)->toHaveCount(3)
            ->and($githubLogs)->toHaveCount(2);
    });

    it('filters logs by status', function () {
        $successLogs = WebhookLog::status('success')->get();
        $failedLogs = WebhookLog::status('failed')->get();

        expect($successLogs)->toHaveCount(3)
            ->and($failedLogs)->toHaveCount(2);
    });

    it('filters logs by event', function () {
        $paymentIntentLogs = WebhookLog::event('payment_intent.succeeded')->get();
        $pushLogs = WebhookLog::event('push')->get();

        expect($paymentIntentLogs)->toHaveCount(2)
            ->and($pushLogs)->toHaveCount(1);
    });

    it('uses successful scope', function () {
        $successfulLogs = WebhookLog::successful()->get();

        expect($successfulLogs)->toHaveCount(3)
            ->and($successfulLogs->every(fn ($log) => $log->status === 'success'))->toBeTrue();
    });

    it('uses failed scope', function () {
        $failedLogs = WebhookLog::failed()->get();

        expect($failedLogs)->toHaveCount(2)
            ->and($failedLogs->every(fn ($log) => $log->status === 'failed'))->toBeTrue();
    });

    it('chains multiple scopes', function () {
        $stripeFailedLogs = WebhookLog::service('stripe')->failed()->get();

        expect($stripeFailedLogs)->toHaveCount(1)
            ->and($stripeFailedLogs->first()->event)->toBe('charge.failed');
    });

    it('filters with multiple conditions', function () {
        $logs = WebhookLog::service('github')
            ->status('failed')
            ->event('pull_request')
            ->get();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->error_message)->toBe('Signature mismatch');
    });
});

describe('WebhookLog timestamps', function () {
    it('stores created_at and updated_at timestamps', function () {
        $log = $this->logger->logSuccess('stripe', 'test.event', ['data' => 'test']);

        expect($log->created_at)->not->toBeNull()
            ->and($log->updated_at)->not->toBeNull()
            ->and($log->created_at->toDateTimeString())->toBe($log->updated_at->toDateTimeString());
    });

    it('can query recent logs', function () {
        $this->logger->logSuccess('stripe', 'event1', ['id' => '1']);
        sleep(1);
        $this->logger->logSuccess('stripe', 'event2', ['id' => '2']);

        $recentLog = WebhookLog::latest()->first();

        expect($recentLog->payload['id'])->toBe('2');
    });
});
