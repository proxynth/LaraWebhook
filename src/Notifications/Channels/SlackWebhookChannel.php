<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Notifications\Channels;

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Custom Slack notification channel using Incoming Webhooks.
 *
 * This channel sends notifications directly to Slack via HTTP
 * without requiring the laravel/slack-notification-channel package.
 */
class SlackWebhookChannel
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        $webhookUrl = $notifiable->routeNotificationFor('slack', $notification);

        if (empty($webhookUrl)) {
            return;
        }

        if (! method_exists($notification, 'toSlack')) {
            return;
        }

        $payload = $notification->toSlack($notifiable);

        if (! is_array($payload)) {
            return;
        }

        try {
            $response = $this->http->post($webhookUrl, $payload);

            if (! $response->successful()) {
                Log::warning('Larawebhook: Failed to send Slack notification', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Larawebhook: Exception sending Slack notification', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
