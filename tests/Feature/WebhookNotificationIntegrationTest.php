<?php

declare(strict_types=1);

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Proxynth\Larawebhook\Events\WebhookNotificationSent;
use Proxynth\Larawebhook\Models\WebhookLog;
use Proxynth\Larawebhook\Notifications\WebhookFailedNotification;
use Proxynth\Larawebhook\Services\FailureDetector;
use Proxynth\Larawebhook\Services\WebhookLogger;

beforeEach(function () {
    Notification::fake();

    config([
        'larawebhook.notifications.enabled' => true,
        'larawebhook.notifications.channels' => ['mail'],
        'larawebhook.notifications.email_recipients' => ['admin@example.com'],
        'larawebhook.notifications.slack_webhook' => null,
        'larawebhook.notifications.failure_threshold' => 3,
        'larawebhook.notifications.failure_window_minutes' => 30,
        'larawebhook.notifications.cooldown_minutes' => 30,
    ]);

    // Clear any cooldowns
    app(FailureDetector::class)->clearCooldown('stripe', 'payment.failed');
    app(FailureDetector::class)->clearCooldown('github', 'push');
});

describe('WebhookLogger notification integration', function () {
    it('sends notification after threshold failures', function () {
        $logger = app(WebhookLogger::class);

        // Log 3 consecutive failures
        for ($i = 0; $i < 3; $i++) {
            $logger->logFailure(
                service: 'stripe',
                event: 'payment.failed',
                payload: ['attempt' => $i],
                errorMessage: 'Connection refused'
            );
        }

        Notification::assertSentTo(new AnonymousNotifiable, WebhookFailedNotification::class);
    });

    it('does not send notification below threshold', function () {
        $logger = app(WebhookLogger::class);

        // Log only 2 failures (below threshold of 3)
        for ($i = 0; $i < 2; $i++) {
            $logger->logFailure(
                service: 'stripe',
                event: 'payment.failed',
                payload: ['attempt' => $i],
                errorMessage: 'Connection refused'
            );
        }

        Notification::assertNothingSent();
    });

    it('does not send notification for successful webhooks', function () {
        $logger = app(WebhookLogger::class);

        // Log 5 successful webhooks
        for ($i = 0; $i < 5; $i++) {
            $logger->logSuccess(
                service: 'stripe',
                event: 'payment.succeeded',
                payload: ['id' => "pi_{$i}"]
            );
        }

        Notification::assertNothingSent();
    });

    it('sends only one notification during cooldown period', function () {
        $logger = app(WebhookLogger::class);

        // Log 6 failures (should trigger 2 times if no cooldown)
        for ($i = 0; $i < 6; $i++) {
            $logger->logFailure(
                service: 'stripe',
                event: 'payment.failed',
                payload: ['attempt' => $i],
                errorMessage: 'Connection refused'
            );
        }

        // Only 1 notification due to cooldown
        Notification::assertSentToTimes(new AnonymousNotifiable, WebhookFailedNotification::class, 1);
    });

    it('sends separate notifications for different service/event combinations', function () {
        $logger = app(WebhookLogger::class);

        // 3 Stripe failures
        for ($i = 0; $i < 3; $i++) {
            $logger->logFailure('stripe', 'payment.failed', ['i' => $i], 'Error');
        }

        // 3 GitHub failures
        for ($i = 0; $i < 3; $i++) {
            $logger->logFailure('github', 'push', ['i' => $i], 'Error');
        }

        // Should have 2 notifications (one per service/event)
        Notification::assertSentToTimes(new AnonymousNotifiable, WebhookFailedNotification::class, 2);
    });

    it('does not send notifications when disabled', function () {
        config(['larawebhook.notifications.enabled' => false]);

        $logger = app(WebhookLogger::class);

        // Log 3 failures
        for ($i = 0; $i < 3; $i++) {
            $logger->logFailure('stripe', 'payment.failed', ['i' => $i], 'Error');
        }

        Notification::assertNothingSent();
    });
});

describe('WebhookLogger returns correct log', function () {
    it('returns the log even when notification is triggered', function () {
        $logger = app(WebhookLogger::class);

        // Create 2 previous failures
        for ($i = 0; $i < 2; $i++) {
            $logger->logFailure('stripe', 'payment.failed', ['i' => $i], 'Error');
        }

        // Third failure should trigger notification and return log
        $log = $logger->logFailure('stripe', 'payment.failed', ['test' => 'data'], 'Final error');

        expect($log)->toBeInstanceOf(WebhookLog::class)
            ->and($log->service)->toBe('stripe')
            ->and($log->event)->toBe('payment.failed')
            ->and($log->status)->toBe('failed')
            ->and($log->error_message)->toBe('Final error');
    });
});

describe('Notification content verification', function () {
    it('notification contains correct failure count', function () {
        $logger = app(WebhookLogger::class);

        // Log 3 failures
        for ($i = 0; $i < 3; $i++) {
            $logger->logFailure('stripe', 'payment.failed', ['i' => $i], 'Error');
        }

        Notification::assertSentTo(
            new AnonymousNotifiable,
            WebhookFailedNotification::class,
            function (WebhookFailedNotification $notification) {
                return $notification->getFailureCount() === 3;
            }
        );
    });

    it('notification references the latest log', function () {
        $logger = app(WebhookLogger::class);

        // Log 3 failures
        $lastLog = null;
        for ($i = 0; $i < 3; $i++) {
            $lastLog = $logger->logFailure('stripe', 'payment.failed', ['i' => $i], "Error {$i}");
        }

        // Verify notification was sent and check log reference
        Notification::assertSentTo(
            new AnonymousNotifiable,
            WebhookFailedNotification::class,
            function (WebhookFailedNotification $notification, $channels, $notifiable) use ($lastLog) {
                // The notification should reference a log for the same service/event
                return $notification->getLog()->service === $lastLog->service
                    && $notification->getLog()->event === $lastLog->event;
            }
        );
    });
});

describe('Event dispatching', function () {
    it('dispatches WebhookNotificationSent event', function () {
        Event::fake([WebhookNotificationSent::class]);

        $logger = app(WebhookLogger::class);

        // Log 3 failures
        for ($i = 0; $i < 3; $i++) {
            $logger->logFailure('stripe', 'payment.failed', ['i' => $i], 'Error');
        }

        Event::assertDispatched(WebhookNotificationSent::class, function ($event) {
            return $event->log->service === 'stripe'
                && $event->log->event === 'payment.failed'
                && $event->failureCount === 3;
        });
    });
});

describe('Time window respecting', function () {
    it('only counts failures within time window', function () {
        $logger = app(WebhookLogger::class);

        // Create 2 old failures (outside window) using raw insert
        for ($i = 0; $i < 2; $i++) {
            WebhookLog::query()->insert([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => json_encode(['test' => $i]),
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ]);
        }

        // Create 2 recent failures (within window but below threshold)
        for ($i = 0; $i < 2; $i++) {
            $logger->logFailure('stripe', 'payment.failed', ['i' => $i], 'Error');
        }

        // Should not trigger notification (only 2 within window)
        Notification::assertNothingSent();
    });
});
