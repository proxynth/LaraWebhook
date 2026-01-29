<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Services;

use Proxynth\Larawebhook\Models\WebhookLog;

class WebhookLogger
{
    public function __construct(
        private readonly ?NotificationSender $notificationSender = null
    ) {}

    /**
     * Log a webhook event.
     *
     * @param  string  $service  Service name (e.g., 'stripe', 'github')
     * @param  string  $event  Event type (e.g., 'payment_intent.succeeded')
     * @param  string  $status  Status ('success' or 'failed')
     * @param  array  $payload  Webhook payload
     * @param  string|null  $errorMessage  Error message if failed
     * @param  int  $attempt  Retry attempt number (0 = first try, 1 = first retry, etc.)
     * @param  string|null  $externalId  External ID for idempotency (provider's event/delivery ID)
     */
    public function log(
        string $service,
        string $event,
        string $status,
        array $payload,
        ?string $errorMessage = null,
        int $attempt = 0,
        ?string $externalId = null
    ): WebhookLog {
        $log = WebhookLog::create([
            'service' => $service,
            'external_id' => $externalId,
            'event' => $event,
            'status' => $status,
            'payload' => $payload,
            'error_message' => $errorMessage,
            'attempt' => $attempt,
        ]);

        // Check for repeated failures and send notification if needed
        if ($status === 'failed') {
            $this->checkAndNotify($service, $event);
        }

        return $log;
    }

    /**
     * Log a successful webhook.
     */
    public function logSuccess(
        string $service,
        string $event,
        array $payload,
        int $attempt = 0,
        ?string $externalId = null
    ): WebhookLog {
        return $this->log($service, $event, 'success', $payload, null, $attempt, $externalId);
    }

    /**
     * Log a failed webhook.
     */
    public function logFailure(
        string $service,
        string $event,
        array $payload,
        string $errorMessage,
        int $attempt = 0,
        ?string $externalId = null
    ): WebhookLog {
        return $this->log($service, $event, 'failed', $payload, $errorMessage, $attempt, $externalId);
    }

    /**
     * Check for repeated failures and send notification if needed.
     */
    private function checkAndNotify(string $service, string $event): void
    {
        $this->notificationSender?->sendIfNeeded($service, $event);
    }
}
