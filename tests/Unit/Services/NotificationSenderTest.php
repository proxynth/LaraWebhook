<?php

declare(strict_types=1);

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Proxynth\Larawebhook\Events\WebhookNotificationSent;
use Proxynth\Larawebhook\Models\WebhookLog;
use Proxynth\Larawebhook\Notifications\WebhookFailedNotification;
use Proxynth\Larawebhook\Services\FailureDetector;
use Proxynth\Larawebhook\Services\NotificationSender;

beforeEach(function () {
    $this->failureDetector = new FailureDetector;
    $this->sender = new NotificationSender($this->failureDetector);

    // Default config
    config([
        'larawebhook.notifications.enabled' => true,
        'larawebhook.notifications.channels' => ['mail'],
        'larawebhook.notifications.email_recipients' => ['admin@example.com'],
        'larawebhook.notifications.slack_webhook' => null,
        'larawebhook.notifications.failure_threshold' => 3,
        'larawebhook.notifications.failure_window_minutes' => 30,
        'larawebhook.notifications.cooldown_minutes' => 30,
    ]);

    // Clear any existing cooldowns
    $this->failureDetector->clearCooldown('stripe', 'payment.failed');
});

describe('NotificationSender send', function () {
    it('sends notification when enabled', function () {
        Notification::fake();

        $log = WebhookLog::create([
            'service' => 'stripe',
            'event' => 'payment.failed',
            'status' => 'failed',
            'payload' => ['test' => 'data'],
        ]);

        $result = $this->sender->send($log, 3);

        expect($result)->toBeTrue();

        Notification::assertSentTo(
            new AnonymousNotifiable,
            WebhookFailedNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notification->getFailureCount() === 3
                    && $notifiable->routes['mail'] === ['admin@example.com'];
            }
        );
    });

    it('does not send when disabled', function () {
        Notification::fake();
        config(['larawebhook.notifications.enabled' => false]);

        $log = WebhookLog::create([
            'service' => 'stripe',
            'event' => 'payment.failed',
            'status' => 'failed',
            'payload' => ['test' => 'data'],
        ]);

        $result = $this->sender->send($log, 3);

        expect($result)->toBeFalse();
        Notification::assertNothingSent();
    });

    it('marks notification sent after sending', function () {
        Notification::fake();

        $log = WebhookLog::create([
            'service' => 'stripe',
            'event' => 'payment.failed',
            'status' => 'failed',
            'payload' => ['test' => 'data'],
        ]);

        $this->sender->send($log, 3);

        expect($this->failureDetector->canSendNotification('stripe', 'payment.failed'))->toBeFalse();
    });
});

describe('NotificationSender sendIfNeeded', function () {
    it('sends notification when threshold reached', function () {
        Notification::fake();

        // Create 3 failures
        for ($i = 0; $i < 3; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => ['test' => $i],
            ]);
        }

        $result = $this->sender->sendIfNeeded('stripe', 'payment.failed');

        expect($result)->toBeTrue();
        Notification::assertSentTo(new AnonymousNotifiable, WebhookFailedNotification::class);
    });

    it('does not send when below threshold', function () {
        Notification::fake();

        // Create 2 failures (below threshold)
        for ($i = 0; $i < 2; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => ['test' => $i],
            ]);
        }

        $result = $this->sender->sendIfNeeded('stripe', 'payment.failed');

        expect($result)->toBeFalse();
        Notification::assertNothingSent();
    });

    it('does not send when disabled', function () {
        Notification::fake();
        config(['larawebhook.notifications.enabled' => false]);

        // Create 3 failures
        for ($i = 0; $i < 3; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => ['test' => $i],
            ]);
        }

        $result = $this->sender->sendIfNeeded('stripe', 'payment.failed');

        expect($result)->toBeFalse();
        Notification::assertNothingSent();
    });

    it('respects cooldown period', function () {
        Notification::fake();

        // Create 3 failures
        for ($i = 0; $i < 3; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => ['test' => $i],
            ]);
        }

        // First notification should be sent
        $result1 = $this->sender->sendIfNeeded('stripe', 'payment.failed');
        expect($result1)->toBeTrue();

        // Second notification should be blocked by cooldown
        $result2 = $this->sender->sendIfNeeded('stripe', 'payment.failed');
        expect($result2)->toBeFalse();

        // Only one notification should have been sent
        Notification::assertSentToTimes(new AnonymousNotifiable, WebhookFailedNotification::class, 1);
    });
});

describe('NotificationSender configuration', function () {
    it('checks enabled status correctly', function () {
        config(['larawebhook.notifications.enabled' => true]);
        expect($this->sender->isEnabled())->toBeTrue();

        config(['larawebhook.notifications.enabled' => false]);
        expect($this->sender->isEnabled())->toBeFalse();
    });

    it('returns configured channels', function () {
        config(['larawebhook.notifications.channels' => ['mail', 'slack']]);

        expect($this->sender->getChannels())->toBe(['mail', 'slack']);
    });

    it('adds slack route when slack webhook is configured', function () {
        Notification::fake();

        config([
            'larawebhook.notifications.slack_webhook' => 'https://hooks.slack.com/services/xxx/yyy/zzz',
            'larawebhook.notifications.channels' => ['mail', 'slack'],
        ]);

        $log = WebhookLog::create([
            'service' => 'stripe',
            'event' => 'payment.failed',
            'status' => 'failed',
            'payload' => ['test' => 'data'],
        ]);

        $result = $this->sender->send($log, 3);

        expect($result)->toBeTrue();

        Notification::assertSentTo(
            new AnonymousNotifiable,
            WebhookFailedNotification::class,
            function ($notification, $channels, $notifiable) {
                // Verify both mail and slack routes are set
                return isset($notifiable->routes['mail'])
                    && isset($notifiable->routes['slack'])
                    && $notifiable->routes['slack'] === 'https://hooks.slack.com/services/xxx/yyy/zzz';
            }
        );
    });

    it('does not add slack route when slack webhook is not configured', function () {
        Notification::fake();

        config([
            'larawebhook.notifications.slack_webhook' => null,
        ]);

        $log = WebhookLog::create([
            'service' => 'stripe',
            'event' => 'payment.failed',
            'status' => 'failed',
            'payload' => ['test' => 'data'],
        ]);

        $result = $this->sender->send($log, 3);

        expect($result)->toBeTrue();

        Notification::assertSentTo(
            new AnonymousNotifiable,
            WebhookFailedNotification::class,
            function ($notification, $channels, $notifiable) {
                // Verify only mail route is set, not slack
                return isset($notifiable->routes['mail'])
                    && ! isset($notifiable->routes['slack']);
            }
        );
    });

    it('does not add slack route when slack webhook is empty string', function () {
        Notification::fake();

        config([
            'larawebhook.notifications.slack_webhook' => '',
        ]);

        $log = WebhookLog::create([
            'service' => 'stripe',
            'event' => 'payment.failed',
            'status' => 'failed',
            'payload' => ['test' => 'data'],
        ]);

        $result = $this->sender->send($log, 3);

        expect($result)->toBeTrue();

        Notification::assertSentTo(
            new AnonymousNotifiable,
            WebhookFailedNotification::class,
            function ($notification, $channels, $notifiable) {
                return isset($notifiable->routes['mail'])
                    && ! isset($notifiable->routes['slack']);
            }
        );
    });
});

describe('NotificationSender events', function () {
    it('dispatches event when notification is sent', function () {
        Notification::fake();
        Event::fake([WebhookNotificationSent::class]);

        // Create sender with event dispatcher
        $sender = app(NotificationSender::class);

        $log = WebhookLog::create([
            'service' => 'stripe',
            'event' => 'payment.failed',
            'status' => 'failed',
            'payload' => ['test' => 'data'],
        ]);

        $sender->send($log, 3);

        Event::assertDispatched(WebhookNotificationSent::class, function ($event) use ($log) {
            return $event->log->id === $log->id && $event->failureCount === 3;
        });
    });
});
