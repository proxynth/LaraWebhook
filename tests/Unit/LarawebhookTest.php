<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Larawebhook;
use Proxynth\Larawebhook\Models\WebhookLog;
use Proxynth\Larawebhook\Services\FailureDetector;

beforeEach(function () {
    $this->larawebhook = new Larawebhook;

    config([
        'larawebhook.services.stripe.webhook_secret' => 'stripe_test_secret',
        'larawebhook.services.github.webhook_secret' => 'github_test_secret',
        'larawebhook.notifications.enabled' => false,
        'larawebhook.notifications.channels' => ['mail'],
        'larawebhook.notifications.failure_threshold' => 3,
        'larawebhook.notifications.failure_window_minutes' => 30,
        'larawebhook.notifications.cooldown_minutes' => 30,
        'larawebhook.retries.enabled' => true,
        'larawebhook.retries.max_attempts' => 3,
        'larawebhook.retries.delays' => [0, 0, 0],
    ]);
});

describe('Larawebhook validation', function () {
    it('validates a correct Stripe webhook', function () {
        $payload = '{"type": "payment_intent.succeeded"}';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, 'stripe_test_secret');
        $signatureHeader = "t={$timestamp},v1={$signature}";

        $result = $this->larawebhook->validate($payload, $signatureHeader, 'stripe');

        expect($result)->toBeTrue();
    });

    it('validates a correct GitHub webhook', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'github_test_secret');

        $result = $this->larawebhook->validate($payload, $signature, 'github');

        expect($result)->toBeTrue();
    });

    it('throws exception for invalid signature', function () {
        $payload = '{"type": "test"}';
        $signatureHeader = 'sha256=invalid_signature';

        expect(fn () => $this->larawebhook->validate($payload, $signatureHeader, 'github'))
            ->toThrow(InvalidSignatureException::class);
    });

    it('throws exception for unsupported service', function () {
        expect(fn () => $this->larawebhook->validate('{}', 'sig', 'unknown'))
            ->toThrow(WebhookException::class, 'No secret configured for service: unknown');
    });

    it('throws exception when secret is not configured', function () {
        config(['larawebhook.services.stripe.webhook_secret' => null]);

        expect(fn () => $this->larawebhook->validate('{}', 'sig', 'stripe'))
            ->toThrow(WebhookException::class, 'No secret configured for service: stripe');
    });
});

describe('Larawebhook validateAndLog', function () {
    it('validates and logs successful webhook', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'github_test_secret');

        $log = $this->larawebhook->validateAndLog($payload, $signature, 'github', 'pull_request.opened');

        expect($log)->toBeInstanceOf(WebhookLog::class)
            ->and($log->status)->toBe('success')
            ->and($log->service)->toBe('github')
            ->and($log->event)->toBe('pull_request.opened');
    });

    it('validates and logs failed webhook', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256=invalid';

        $log = $this->larawebhook->validateAndLog($payload, $signature, 'github', 'pull_request.opened');

        expect($log)->toBeInstanceOf(WebhookLog::class)
            ->and($log->status)->toBe('failed')
            ->and($log->error_message)->not->toBeNull();
    });
});

describe('Larawebhook validateWithRetries', function () {
    it('succeeds on first attempt with valid signature', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'github_test_secret');

        $log = $this->larawebhook->validateWithRetries($payload, $signature, 'github', 'test.event');

        expect($log->status)->toBe('success')
            ->and($log->attempt)->toBe(0);
    });

    it('throws exception after all retries fail', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256=invalid';

        expect(fn () => $this->larawebhook->validateWithRetries($payload, $signature, 'github', 'test.event'))
            ->toThrow(InvalidSignatureException::class);

        // Should have 3 failed logs
        expect(WebhookLog::count())->toBe(3);
    });
});

describe('Larawebhook logging', function () {
    it('logs success', function () {
        $log = $this->larawebhook->logSuccess('stripe', 'payment.succeeded', ['amount' => 1000]);

        expect($log->service)->toBe('stripe')
            ->and($log->event)->toBe('payment.succeeded')
            ->and($log->status)->toBe('success')
            ->and($log->payload['amount'])->toBe(1000);
    });

    it('logs failure', function () {
        $log = $this->larawebhook->logFailure('stripe', 'payment.failed', ['id' => 'pi_123'], 'Payment declined');

        expect($log->service)->toBe('stripe')
            ->and($log->event)->toBe('payment.failed')
            ->and($log->status)->toBe('failed')
            ->and($log->error_message)->toBe('Payment declined');
    });

    it('logs with attempt number', function () {
        $log = $this->larawebhook->logSuccess('github', 'push', ['ref' => 'main'], 2);

        expect($log->attempt)->toBe(2);
    });
});

describe('Larawebhook log queries', function () {
    beforeEach(function () {
        WebhookLog::query()->delete();

        // Create test logs
        WebhookLog::create(['service' => 'stripe', 'event' => 'payment.succeeded', 'status' => 'success', 'payload' => []]);
        WebhookLog::create(['service' => 'stripe', 'event' => 'payment.failed', 'status' => 'failed', 'payload' => [], 'error_message' => 'Error']);
        WebhookLog::create(['service' => 'github', 'event' => 'push', 'status' => 'success', 'payload' => []]);
        WebhookLog::create(['service' => 'github', 'event' => 'pull_request', 'status' => 'failed', 'payload' => [], 'error_message' => 'Error']);
    });

    it('gets all logs', function () {
        $logs = $this->larawebhook->logs();

        expect($logs)->toHaveCount(4);
    });

    it('gets logs for a specific service', function () {
        $logs = $this->larawebhook->logsForService('stripe');

        expect($logs)->toHaveCount(2)
            ->and($logs->pluck('service')->unique()->toArray())->toBe(['stripe']);
    });

    it('gets failed logs', function () {
        $logs = $this->larawebhook->failedLogs();

        expect($logs)->toHaveCount(2)
            ->and($logs->pluck('status')->unique()->toArray())->toBe(['failed']);
    });

    it('gets successful logs', function () {
        $logs = $this->larawebhook->successfulLogs();

        expect($logs)->toHaveCount(2)
            ->and($logs->pluck('status')->unique()->toArray())->toBe(['success']);
    });
});

describe('Larawebhook failure detection', function () {
    beforeEach(function () {
        WebhookLog::query()->delete();
        app(FailureDetector::class)->clearCooldown('stripe', 'payment.failed');
    });

    it('gets failure count', function () {
        // Create 3 failed logs
        for ($i = 0; $i < 3; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => [],
            ]);
        }

        $count = $this->larawebhook->getFailureCount('stripe', 'payment.failed');

        expect($count)->toBe(3);
    });

    it('checks if notification can be sent', function () {
        expect($this->larawebhook->canSendNotification('stripe', 'payment.failed'))->toBeTrue();
    });

    it('clears cooldown', function () {
        // Mark notification as sent (sets cooldown)
        app(FailureDetector::class)->markNotificationSent('stripe', 'payment.failed');

        expect($this->larawebhook->canSendNotification('stripe', 'payment.failed'))->toBeFalse();

        $this->larawebhook->clearCooldown('stripe', 'payment.failed');

        expect($this->larawebhook->canSendNotification('stripe', 'payment.failed'))->toBeTrue();
    });
});

describe('Larawebhook notifications', function () {
    beforeEach(function () {
        Notification::fake();
        WebhookLog::query()->delete();
        app(FailureDetector::class)->clearCooldown('stripe', 'payment.failed');
    });

    it('sends notification if needed when threshold reached', function () {
        config(['larawebhook.notifications.enabled' => true]);

        // Create enough failures to trigger notification
        for ($i = 0; $i < 3; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => [],
            ]);
        }

        $result = $this->larawebhook->sendNotificationIfNeeded('stripe', 'payment.failed');

        expect($result)->toBeTrue();
    });

    it('does not send notification when disabled', function () {
        config(['larawebhook.notifications.enabled' => false]);

        $result = $this->larawebhook->sendNotificationIfNeeded('stripe', 'payment.failed');

        expect($result)->toBeFalse();
    });

    it('checks if notifications are enabled', function () {
        config(['larawebhook.notifications.enabled' => true]);
        expect($this->larawebhook->notificationsEnabled())->toBeTrue();

        config(['larawebhook.notifications.enabled' => false]);
        expect($this->larawebhook->notificationsEnabled())->toBeFalse();
    });

    it('gets notification channels', function () {
        config(['larawebhook.notifications.channels' => ['mail', 'slack']]);

        expect($this->larawebhook->getNotificationChannels())->toBe(['mail', 'slack']);
    });
});

describe('Larawebhook configuration helpers', function () {
    it('gets secret for a service', function () {
        expect($this->larawebhook->getSecret('stripe'))->toBe('stripe_test_secret')
            ->and($this->larawebhook->getSecret('github'))->toBe('github_test_secret')
            ->and($this->larawebhook->getSecret('unknown'))->toBeNull();
    });

    it('checks if service is supported', function () {
        expect($this->larawebhook->isServiceSupported('stripe'))->toBeTrue()
            ->and($this->larawebhook->isServiceSupported('github'))->toBeTrue()
            ->and($this->larawebhook->isServiceSupported('unknown'))->toBeFalse();
    });

    it('returns supported services list', function () {
        expect($this->larawebhook->supportedServices())->toBe(['stripe', 'github']);
    });
});

describe('Larawebhook facade usage', function () {
    it('can be used via facade', function () {
        expect(\Proxynth\Larawebhook\Facades\Larawebhook::supportedServices())->toBe(['stripe', 'github']);
    });

    it('facade provides same functionality', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'github_test_secret');

        $result = \Proxynth\Larawebhook\Facades\Larawebhook::validate($payload, $signature, 'github');

        expect($result)->toBeTrue();
    });
});
