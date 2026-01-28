<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Models\WebhookLog;
use Proxynth\Larawebhook\Services\FailureDetector;

beforeEach(function () {
    $this->detector = new FailureDetector;

    // Default config
    config([
        'larawebhook.notifications.enabled' => true,
        'larawebhook.notifications.failure_threshold' => 3,
        'larawebhook.notifications.failure_window_minutes' => 30,
        'larawebhook.notifications.cooldown_minutes' => 30,
    ]);

    // Clear any existing cooldowns
    $this->detector->clearCooldown('stripe', 'payment.failed');
    $this->detector->clearCooldown('stripe', 'payment.succeeded');
    $this->detector->clearCooldown('github', 'push');
});

describe('FailureDetector counting failures', function () {
    it('counts recent failures correctly', function () {
        // Create 3 failures within window
        for ($i = 0; $i < 3; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => ['test' => $i],
            ]);
        }

        $count = $this->detector->countRecentFailures('stripe', 'payment.failed');

        expect($count)->toBe(3);
    });

    it('ignores failures outside time window', function () {
        // Create 2 recent failures
        for ($i = 0; $i < 2; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => ['test' => $i],
            ]);
        }

        // Create 1 old failure (outside 30 min window)
        WebhookLog::query()->insert([
            'service' => 'stripe',
            'event' => 'payment.failed',
            'status' => 'failed',
            'payload' => json_encode(['test' => 'old']),
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        $count = $this->detector->countRecentFailures('stripe', 'payment.failed');

        expect($count)->toBe(2);
    });

    it('ignores successful webhooks', function () {
        WebhookLog::create([
            'service' => 'stripe',
            'event' => 'payment.succeeded',
            'status' => 'success',
            'payload' => ['test' => 1],
        ]);

        WebhookLog::create([
            'service' => 'stripe',
            'event' => 'payment.succeeded',
            'status' => 'failed',
            'payload' => ['test' => 2],
        ]);

        $count = $this->detector->countRecentFailures('stripe', 'payment.succeeded');

        expect($count)->toBe(1);
    });

    it('counts failures per service and event separately', function () {
        // Stripe failures
        for ($i = 0; $i < 3; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => ['test' => $i],
            ]);
        }

        // GitHub failures
        for ($i = 0; $i < 2; $i++) {
            WebhookLog::create([
                'service' => 'github',
                'event' => 'push',
                'status' => 'failed',
                'payload' => ['test' => $i],
            ]);
        }

        expect($this->detector->countRecentFailures('stripe', 'payment.failed'))->toBe(3)
            ->and($this->detector->countRecentFailures('github', 'push'))->toBe(2)
            ->and($this->detector->countRecentFailures('stripe', 'push'))->toBe(0);
    });
});

describe('FailureDetector notification check', function () {
    it('returns should_notify true when threshold is reached', function () {
        // Create 3 failures (threshold)
        for ($i = 0; $i < 3; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => ['test' => $i],
            ]);
        }

        $result = $this->detector->checkForRepeatedFailures('stripe', 'payment.failed');

        expect($result['should_notify'])->toBeTrue()
            ->and($result['failure_count'])->toBe(3)
            ->and($result['latest_log'])->toBeInstanceOf(WebhookLog::class);
    });

    it('returns should_notify false below threshold', function () {
        // Create 2 failures (below threshold of 3)
        for ($i = 0; $i < 2; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => ['test' => $i],
            ]);
        }

        $result = $this->detector->checkForRepeatedFailures('stripe', 'payment.failed');

        expect($result['should_notify'])->toBeFalse()
            ->and($result['failure_count'])->toBe(2);
    });

    it('returns should_notify false when no failures exist', function () {
        $result = $this->detector->checkForRepeatedFailures('stripe', 'payment.failed');

        expect($result['should_notify'])->toBeFalse()
            ->and($result['failure_count'])->toBe(0)
            ->and($result['latest_log'])->toBeNull();
    });
});

describe('FailureDetector cooldown management', function () {
    it('allows notification when no cooldown exists', function () {
        $canSend = $this->detector->canSendNotification('stripe', 'payment.failed');

        expect($canSend)->toBeTrue();
    });

    it('prevents notification during cooldown period', function () {
        // Mark notification as sent
        $this->detector->markNotificationSent('stripe', 'payment.failed');

        $canSend = $this->detector->canSendNotification('stripe', 'payment.failed');

        expect($canSend)->toBeFalse();
    });

    it('allows notification after cooldown expires', function () {
        // Set a very short cooldown for testing
        config(['larawebhook.notifications.cooldown_minutes' => 0]);

        $this->detector->markNotificationSent('stripe', 'payment.failed');

        // Wait a moment (cooldown is 0 minutes)
        $canSend = $this->detector->canSendNotification('stripe', 'payment.failed');

        expect($canSend)->toBeTrue();
    });

    it('clears cooldown correctly', function () {
        $this->detector->markNotificationSent('stripe', 'payment.failed');
        expect($this->detector->canSendNotification('stripe', 'payment.failed'))->toBeFalse();

        $this->detector->clearCooldown('stripe', 'payment.failed');
        expect($this->detector->canSendNotification('stripe', 'payment.failed'))->toBeTrue();
    });

    it('maintains separate cooldowns for different service/event combinations', function () {
        $this->detector->markNotificationSent('stripe', 'payment.failed');

        expect($this->detector->canSendNotification('stripe', 'payment.failed'))->toBeFalse()
            ->and($this->detector->canSendNotification('stripe', 'customer.created'))->toBeTrue()
            ->and($this->detector->canSendNotification('github', 'payment.failed'))->toBeTrue();
    });
});

describe('FailureDetector with custom thresholds', function () {
    it('respects custom failure threshold', function () {
        config(['larawebhook.notifications.failure_threshold' => 5]);

        // Create 4 failures (below new threshold of 5)
        for ($i = 0; $i < 4; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => ['test' => $i],
            ]);
        }

        $result = $this->detector->checkForRepeatedFailures('stripe', 'payment.failed');

        expect($result['should_notify'])->toBeFalse()
            ->and($result['failure_count'])->toBe(4);
    });

    it('respects custom time window', function () {
        config(['larawebhook.notifications.failure_window_minutes' => 10]);

        // Create 2 failures within 10 minutes
        for ($i = 0; $i < 2; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => ['test' => $i],
            ]);
        }

        // Create 1 failure at 15 minutes ago (outside window)
        WebhookLog::query()->insert([
            'service' => 'stripe',
            'event' => 'payment.failed',
            'status' => 'failed',
            'payload' => json_encode(['test' => 'old']),
            'created_at' => now()->subMinutes(15),
            'updated_at' => now()->subMinutes(15),
        ]);

        $count = $this->detector->countRecentFailures('stripe', 'payment.failed');

        expect($count)->toBe(2);
    });
});
