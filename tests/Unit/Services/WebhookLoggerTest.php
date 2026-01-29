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

describe('WebhookLogger external_id support', function () {
    beforeEach(function () {
        $this->logger = new WebhookLogger;
    });

    it('logs webhook with external_id', function () {
        $log = $this->logger->log(
            service: 'stripe',
            event: 'payment_intent.succeeded',
            status: 'success',
            payload: ['id' => 'pi_123'],
            externalId: 'evt_1234567890'
        );

        expect($log->external_id)->toBe('evt_1234567890');
    });

    it('logs success with external_id', function () {
        $log = $this->logger->logSuccess(
            service: 'github',
            event: 'push',
            payload: ['ref' => 'main'],
            externalId: 'abc123-delivery-id'
        );

        expect($log->external_id)->toBe('abc123-delivery-id');
    });

    it('logs failure with external_id', function () {
        $log = $this->logger->logFailure(
            service: 'shopify',
            event: 'orders/create',
            payload: ['id' => 123],
            errorMessage: 'Invalid signature',
            externalId: 'shopify-webhook-id-123'
        );

        expect($log->external_id)->toBe('shopify-webhook-id-123');
    });

    it('stores null external_id when not provided', function () {
        $log = $this->logger->logSuccess(
            service: 'stripe',
            event: 'payment_intent.succeeded',
            payload: ['id' => 'pi_123']
        );

        expect($log->external_id)->toBeNull();
    });
});

describe('WebhookLog external_id model methods', function () {
    beforeEach(function () {
        $this->logger = new WebhookLogger;
    });

    it('checks if webhook exists for external_id', function () {
        $this->logger->logSuccess('stripe', 'payment.success', ['id' => '1'], 0, 'evt_existing');

        expect(WebhookLog::existsForExternalId('stripe', 'evt_existing'))->toBeTrue()
            ->and(WebhookLog::existsForExternalId('stripe', 'evt_nonexistent'))->toBeFalse()
            ->and(WebhookLog::existsForExternalId('github', 'evt_existing'))->toBeFalse();
    });

    it('finds webhook by service and external_id', function () {
        $this->logger->logSuccess('stripe', 'payment.success', ['amount' => 1000], 0, 'evt_findable');

        $found = WebhookLog::findByExternalId('stripe', 'evt_findable');

        expect($found)->not->toBeNull()
            ->and($found->payload['amount'])->toBe(1000);
    });

    it('returns null when webhook not found by external_id', function () {
        $found = WebhookLog::findByExternalId('stripe', 'evt_nonexistent');

        expect($found)->toBeNull();
    });

    it('filters by external_id scope', function () {
        $this->logger->logSuccess('stripe', 'event1', ['id' => '1'], 0, 'evt_a');
        $this->logger->logSuccess('stripe', 'event2', ['id' => '2'], 0, 'evt_b');
        $this->logger->logSuccess('github', 'event3', ['id' => '3'], 0, 'evt_a');

        $logs = WebhookLog::externalId('evt_a')->get();

        expect($logs)->toHaveCount(2);
    });

    it('enforces unique constraint on service + external_id', function () {
        $this->logger->logSuccess('stripe', 'event1', ['id' => '1'], 0, 'evt_unique');

        // Attempting to create another log with same service + external_id should fail
        expect(fn () => $this->logger->logSuccess('stripe', 'event2', ['id' => '2'], 0, 'evt_unique'))
            ->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('allows same external_id for different services', function () {
        $this->logger->logSuccess('stripe', 'event1', ['id' => '1'], 0, 'same_external_id');
        $this->logger->logSuccess('github', 'event2', ['id' => '2'], 0, 'same_external_id');

        $stripeLogs = WebhookLog::service('stripe')->externalId('same_external_id')->get();
        $githubLogs = WebhookLog::service('github')->externalId('same_external_id')->get();

        expect($stripeLogs)->toHaveCount(1)
            ->and($githubLogs)->toHaveCount(1);
    });
});
