<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Proxynth\Larawebhook\Events\WebhookNotificationSent;
use Proxynth\Larawebhook\Models\WebhookLog;
use Proxynth\Larawebhook\Notifications\WebhookFailedNotification;

/**
 * Sends notifications for webhook failures.
 *
 * This service is responsible for:
 * - Creating and sending notifications through configured channels
 * - Respecting notification enable/disable configuration
 * - Dispatching events after notifications are sent
 */
class NotificationSender
{
    public function __construct(
        private readonly FailureDetector $failureDetector,
        private readonly ?Dispatcher $eventDispatcher = null
    ) {}

    /**
     * Send a failure notification if conditions are met.
     */
    public function sendIfNeeded(string $service, string $event): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $result = $this->failureDetector->checkForRepeatedFailures($service, $event);

        if (! $result['should_notify'] || $result['latest_log'] === null) {
            return false;
        }

        return $this->send($result['latest_log'], $result['failure_count']);
    }

    /**
     * Send a failure notification.
     */
    public function send(WebhookLog $log, int $failureCount): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $notification = new WebhookFailedNotification($log, $failureCount);

        // Build the notification route using Laravel's on-demand notifications
        $route = Notification::route('mail', $this->getEmailRecipients());

        $slackWebhook = $this->getSlackWebhook();
        if ($slackWebhook) {
            $route = $route->route('slack', $slackWebhook);
        }

        $route->notify($notification);

        // Mark that we've sent a notification to respect cooldown
        $this->failureDetector->markNotificationSent($log->service, $log->event);

        // Dispatch event
        $this->dispatchEvent($log, $failureCount);

        return true;
    }

    /**
     * Check if notifications are enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) config('larawebhook.notifications.enabled', false);
    }

    /**
     * Get configured notification channels.
     *
     * @return array<string>
     */
    public function getChannels(): array
    {
        return config('larawebhook.notifications.channels', ['mail']);
    }

    /**
     * Get email recipients from config.
     *
     * @return array<string>
     */
    private function getEmailRecipients(): array
    {
        $recipients = config('larawebhook.notifications.email_recipients', []);

        return array_filter((array) $recipients);
    }

    /**
     * Get Slack webhook URL from config.
     */
    private function getSlackWebhook(): ?string
    {
        $webhook = config('larawebhook.notifications.slack_webhook');

        return $webhook ?: null;
    }

    /**
     * Dispatch the notification sent event.
     */
    private function dispatchEvent(WebhookLog $log, int $failureCount): void
    {
        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch(
                new WebhookNotificationSent($log, $failureCount)
            );
        }
    }
}
