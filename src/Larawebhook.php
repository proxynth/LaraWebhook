<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook;

use Illuminate\Database\Eloquent\Collection;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Models\WebhookLog;
use Proxynth\Larawebhook\Services\FailureDetector;
use Proxynth\Larawebhook\Services\NotificationSender;
use Proxynth\Larawebhook\Services\WebhookLogger;
use Proxynth\Larawebhook\Services\WebhookValidator;

/**
 * Main entry point for the Larawebhook package.
 *
 * Provides a fluent API for webhook validation, logging, and querying.
 */
class Larawebhook
{
    private ?WebhookValidator $validator = null;

    private ?WebhookLogger $logger = null;

    private ?NotificationSender $notificationSender = null;

    private ?FailureDetector $failureDetector = null;

    /**
     * Validate a webhook signature.
     *
     * @throws InvalidSignatureException
     * @throws WebhookException
     */
    public function validate(string $payload, string $signature, string $service): bool
    {
        return $this->getValidator($service)->validate($payload, $signature, $service);
    }

    /**
     * Validate a webhook and log the result.
     */
    public function validateAndLog(
        string $payload,
        string $signature,
        string $service,
        string $event
    ): WebhookLog {
        return $this->getValidator($service)->validateAndLog($payload, $signature, $service, $event);
    }

    /**
     * Validate a webhook with automatic retries.
     *
     * @throws InvalidSignatureException
     * @throws WebhookException
     */
    public function validateWithRetries(
        string $payload,
        string $signature,
        string $service,
        string $event
    ): WebhookLog {
        return $this->getValidator($service)->validateWithRetries($payload, $signature, $service, $event);
    }

    /**
     * Log a successful webhook.
     */
    public function logSuccess(string $service, string $event, array $payload, int $attempt = 0): WebhookLog
    {
        return $this->getLogger()->logSuccess($service, $event, $payload, $attempt);
    }

    /**
     * Log a failed webhook.
     */
    public function logFailure(
        string $service,
        string $event,
        array $payload,
        string $errorMessage,
        int $attempt = 0
    ): WebhookLog {
        return $this->getLogger()->logFailure($service, $event, $payload, $errorMessage, $attempt);
    }

    /**
     * Get all webhook logs.
     *
     * @return Collection<int, WebhookLog>
     */
    public function logs(): Collection
    {
        return WebhookLog::latest()->get();
    }

    /**
     * Get webhook logs for a specific service.
     *
     * @return Collection<int, WebhookLog>
     */
    public function logsForService(string $service): Collection
    {
        return WebhookLog::service($service)->latest()->get();
    }

    /**
     * Get failed webhook logs.
     *
     * @return Collection<int, WebhookLog>
     */
    public function failedLogs(): Collection
    {
        return WebhookLog::failed()->latest()->get();
    }

    /**
     * Get successful webhook logs.
     *
     * @return Collection<int, WebhookLog>
     */
    public function successfulLogs(): Collection
    {
        return WebhookLog::successful()->latest()->get();
    }

    /**
     * Get the count of failed webhooks for a service/event in the time window.
     */
    public function getFailureCount(string $service, string $event): int
    {
        return $this->getFailureDetector()->countRecentFailures($service, $event);
    }

    /**
     * Check if a notification can be sent (respects cooldown).
     */
    public function canSendNotification(string $service, string $event): bool
    {
        return $this->getFailureDetector()->canSendNotification($service, $event);
    }

    /**
     * Send a failure notification if conditions are met.
     */
    public function sendNotificationIfNeeded(string $service, string $event): bool
    {
        return $this->getNotificationSender()->sendIfNeeded($service, $event);
    }

    /**
     * Check if notifications are enabled.
     */
    public function notificationsEnabled(): bool
    {
        return $this->getNotificationSender()->isEnabled();
    }

    /**
     * Get the configured notification channels.
     *
     * @return array<string>
     */
    public function getNotificationChannels(): array
    {
        return $this->getNotificationSender()->getChannels();
    }

    /**
     * Clear the notification cooldown for a service/event.
     */
    public function clearCooldown(string $service, string $event): void
    {
        $this->getFailureDetector()->clearCooldown($service, $event);
    }

    /**
     * Get the secret for a service from config.
     */
    public function getSecret(string $service): ?string
    {
        return config("larawebhook.services.{$service}.webhook_secret");
    }

    /**
     * Check if a service is supported.
     */
    public function isServiceSupported(string $service): bool
    {
        return in_array($service, ['stripe', 'github'], true);
    }

    /**
     * Get the list of supported services.
     *
     * @return array<string>
     */
    public function supportedServices(): array
    {
        return ['stripe', 'github'];
    }

    /**
     * Get a validator instance for a service.
     */
    private function getValidator(string $service): WebhookValidator
    {
        $secret = $this->getSecret($service);

        if ($secret === null) {
            throw new WebhookException("No secret configured for service: {$service}");
        }

        if ($this->validator === null || $this->validator !== $this->validator) {
            $this->validator = new WebhookValidator($secret, 300, $this->getLogger());
        }

        return $this->validator;
    }

    /**
     * Get the logger instance.
     */
    private function getLogger(): WebhookLogger
    {
        if ($this->logger === null) {
            $this->logger = app(WebhookLogger::class);
        }

        return $this->logger;
    }

    /**
     * Get the notification sender instance.
     */
    private function getNotificationSender(): NotificationSender
    {
        if ($this->notificationSender === null) {
            $this->notificationSender = app(NotificationSender::class);
        }

        return $this->notificationSender;
    }

    /**
     * Get the failure detector instance.
     */
    private function getFailureDetector(): FailureDetector
    {
        if ($this->failureDetector === null) {
            $this->failureDetector = app(FailureDetector::class);
        }

        return $this->failureDetector;
    }
}
