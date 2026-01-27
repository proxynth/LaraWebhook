<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Services;

use Proxynth\Larawebhook\Models\WebhookLog;

class WebhookLogger
{
    /**
     * Log a webhook event.
     *
     * @param  string  $service  Service name (e.g., 'stripe', 'github')
     * @param  string  $event  Event type (e.g., 'payment_intent.succeeded')
     * @param  string  $status  Status ('success' or 'failed')
     * @param  array  $payload  Webhook payload
     * @param  string|null  $errorMessage  Error message if failed
     */
    public function log(
        string $service,
        string $event,
        string $status,
        array $payload,
        ?string $errorMessage = null
    ): WebhookLog {
        return WebhookLog::create([
            'service' => $service,
            'event' => $event,
            'status' => $status,
            'payload' => $payload,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Log a successful webhook.
     */
    public function logSuccess(string $service, string $event, array $payload): WebhookLog
    {
        return $this->log($service, $event, 'success', $payload);
    }

    /**
     * Log a failed webhook.
     */
    public function logFailure(
        string $service,
        string $event,
        array $payload,
        string $errorMessage
    ): WebhookLog {
        return $this->log($service, $event, 'failed', $payload, $errorMessage);
    }
}
