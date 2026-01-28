<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Proxynth\Larawebhook\Models\WebhookLog;

class WebhookFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly WebhookLog $log,
        private readonly int $failureCount
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = config('larawebhook.notifications.channels');

        if (! is_array($channels) || empty($channels)) {
            return ['mail'];
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $dashboardUrl = $this->getDashboardUrl();

        return (new MailMessage)
            ->subject("Webhook Failure Alert: {$this->log->service}")
            ->error()
            ->greeting('Webhook Failure Detected')
            ->line("The webhook **{$this->log->event}** has failed **{$this->failureCount} times**.")
            ->line("**Service:** {$this->log->service}")
            ->line("**Event:** {$this->log->event}")
            ->line("**Last attempt:** {$this->log->created_at->format('Y-m-d H:i:s')}")
            ->when($this->log->error_message, function (MailMessage $message) {
                return $message->line("**Error:** {$this->log->error_message}");
            })
            ->action('View Dashboard', $dashboardUrl)
            ->line('Please check the webhook configuration and investigate the issue.');
    }

    /**
     * Get the Slack representation of the notification.
     *
     * Returns an array payload for Slack webhook.
     * This uses a simple array format compatible with Slack's Incoming Webhooks
     * without requiring the laravel/slack-notification-channel package.
     *
     * @return array<string, mixed>
     */
    public function toSlack(mixed $notifiable): array
    {
        $dashboardUrl = $this->getDashboardUrl();

        return [
            'text' => 'Webhook Failure Alert: Repeated failures detected',
            'attachments' => [
                [
                    'color' => 'danger',
                    'title' => "Service: {$this->log->service}",
                    'fields' => [
                        [
                            'title' => 'Event',
                            'value' => $this->log->event,
                            'short' => true,
                        ],
                        [
                            'title' => 'Failure Count',
                            'value' => (string) $this->failureCount,
                            'short' => true,
                        ],
                        [
                            'title' => 'Last Attempt',
                            'value' => $this->log->created_at->format('Y-m-d H:i:s'),
                            'short' => true,
                        ],
                        [
                            'title' => 'Error',
                            'value' => $this->log->error_message ?? 'N/A',
                            'short' => true,
                        ],
                    ],
                    'actions' => [
                        [
                            'type' => 'button',
                            'text' => 'View Dashboard',
                            'url' => $dashboardUrl,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'service' => $this->log->service,
            'event' => $this->log->event,
            'failure_count' => $this->failureCount,
            'last_attempt' => $this->log->created_at->toIso8601String(),
            'error_message' => $this->log->error_message,
            'log_id' => $this->log->id,
        ];
    }

    /**
     * Get the webhook log associated with this notification.
     */
    public function getLog(): WebhookLog
    {
        return $this->log;
    }

    /**
     * Get the failure count.
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Get the dashboard URL with log filter.
     */
    private function getDashboardUrl(): string
    {
        $dashboardPath = config('larawebhook.dashboard.path', '/larawebhook/dashboard');

        return url("{$dashboardPath}?log={$this->log->id}");
    }
}
