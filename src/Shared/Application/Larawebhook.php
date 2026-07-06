<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Shared\Application;

use Illuminate\Database\Eloquent\Collection;
use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\UseCases\RecordWebhookLog;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Notifications\FailureDetector;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Notifications\NotificationSender;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ValidateWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookPayloadParserResolver;
use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookServiceMetadataResolver;
use Proxynth\Larawebhook\Ingestion\Application\UseCases\ValidateWebhook;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\DeliveryAttempt;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

/**
 * Main entry point for the Larawebhook package.
 *
 * Provides a fluent API for webhook validation, logging, and querying.
 * Prefer validate(), validateAndLog(), validateWithRetries(), and the read helpers.
 */
class Larawebhook
{
    private ?ValidateWebhook $validateWebhook = null;

    private ?RecordWebhookLog $recordWebhookLog = null;

    private ?WebhookServiceMetadataResolver $metadataResolver = null;

    private ?WebhookPayloadParserResolver $payloadParserResolver = null;

    private ?NotificationSender $notificationSender = null;

    private ?FailureDetector $failureDetector = null;

    /**
     * Validate a webhook signature.
     *
     * @throws InvalidSignatureException
     * @throws WebhookException
     */
    public function validate(string $payload, Signature $signature, string|WebhookService $service): bool
    {
        $webhookService = $this->resolveService($service);
        $rawPayload = RawPayload::fromString($payload);

        $secret = $this->getMetadataResolver()->secret($webhookService);

        if ($secret === null || $secret === '') {
            throw new WebhookException("No secret configured for service: {$webhookService->value}");
        }

        $result = $this->getValidateWebhook()->handle(new ValidateWebhookCommand(
            service: $webhookService,
            payload: $rawPayload,
            signature: $signature,
            event: $this->extractEvent($webhookService, $rawPayload),
            externalId: $this->extractExternalId($webhookService, $rawPayload),
            secret: $secret,
        ));

        if ($result->isInvalid()) {
            throw new InvalidSignatureException($result->errorMessage ?? 'Invalid webhook signature.');
        }

        return true;
    }

    /**
     * Validate a webhook and log the result.
     *
     * @throws WebhookException
     */
    public function validateAndLog(
        string $payload,
        Signature $signature,
        string|WebhookService $service,
        string $event
    ): WebhookLog {
        return $this->validateAndRecord(
            payload: $payload,
            signature: $signature,
            service: $service,
            event: $event,
            attempt: DeliveryAttempt::initial()->value(),
        );
    }

    /**
     * Validate a webhook with automatic retries.
     *
     * @throws InvalidSignatureException
     * @throws WebhookException
     */
    public function validateWithRetries(
        string $payload,
        Signature $signature,
        string|WebhookService $service,
        string $event
    ): WebhookLog {
        $webhookService = $this->resolveService($service);
        $maxAttempts = (int) config('larawebhook.retries.max_attempts', 3);

        if ($maxAttempts <= 0) {
            throw new WebhookException('Validation failed with no recorded exception.');
        }

        $delays = config('larawebhook.retries.delays', [1, 5, 10]);
        $lastLog = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $lastLog = $this->validateAndRecord(
                payload: $payload,
                signature: $signature,
                service: $webhookService,
                event: $event,
                attempt: $attempt,
                externalId: $this->extractExternalId($webhookService, RawPayload::fromString($payload)),
            );

            if ($lastLog->status !== 'failed') {
                return $lastLog;
            }

            if ($attempt < $maxAttempts - 1) {
                $delay = is_array($delays) && isset($delays[$attempt])
                    ? (int) $delays[$attempt]
                    : 0;

                if ($delay > 0) {
                    sleep($delay);
                }
            }
        }

        throw new InvalidSignatureException(
            $lastLog->error_message ?? 'Webhook validation failed after all retries.'
        );
    }

    /**
     * Log a successful webhook.
     *
     * @deprecated Use validateAndLog() or the corresponding application use case.
     */
    public function logSuccess(
        string $service,
        string $event,
        array $payload,
        int $attempt = 0,
        ?string $idempotencyKey = null
    ): WebhookLog {
        return $this->getRecordWebhookLog()->handle(new RecordWebhookLogCommand(
            service: $service,
            event: $event,
            valid: true,
            payload: $payload,
            attempt: $attempt,
            idempotencyKey: $idempotencyKey,
        ));
    }

    /**
     * Log a failed webhook.
     *
     * @deprecated Use validateAndLog() or the corresponding application use case.
     */
    public function logFailure(
        string $service,
        string $event,
        array $payload,
        string $errorMessage,
        int $attempt = 0,
        ?string $idempotencyKey = null
    ): WebhookLog {
        return $this->getRecordWebhookLog()->handle(new RecordWebhookLogCommand(
            service: $service,
            event: $event,
            valid: false,
            payload: $payload,
            attempt: $attempt,
            idempotencyKey: $idempotencyKey,
            errorMessage: $errorMessage,
        ));
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
     *
     * @throws WebhookException
     */
    public function logsForService(string|WebhookService $service): Collection
    {
        $service = $this->resolveService($service);

        return WebhookLog::service($service->value)->latest()->get();
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
    public function getSecret(string|WebhookService $service): ?string
    {
        $webhookService = $service instanceof WebhookService
            ? $service
            : WebhookService::tryFromString($service);

        if ($webhookService === null) {
            return null;
        }

        return $this->getMetadataResolver()->secret($webhookService);
    }

    /**
     * Check if a service is supported.
     */
    public function isServiceSupported(string $service): bool
    {
        return WebhookService::isSupported($service);
    }

    /**
     * Get the list of supported services.
     *
     * @return array<string>
     */
    public function supportedServices(): array
    {
        return WebhookService::values();
    }

    /**
     * Get all webhook service enum cases.
     *
     * @return array<WebhookService>
     */
    public function services(): array
    {
        return WebhookService::cases();
    }

    /**
     * Get a service enum from string.
     */
    public function service(string $service): ?WebhookService
    {
        return WebhookService::tryFromString($service);
    }

    /**
     * Resolve service from string or enum.
     *
     * @throws WebhookException
     */
    private function resolveService(string|WebhookService $service): WebhookService
    {
        if ($service instanceof WebhookService) {
            return $service;
        }

        if (! WebhookService::isSupported($service)) {
            throw new WebhookException("Webhook service '{$service}' is not supported");
        }

        return WebhookService::fromString($service);
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

    private function getValidateWebhook(): ValidateWebhook
    {
        if ($this->validateWebhook === null) {
            $this->validateWebhook = app(ValidateWebhook::class);
        }

        return $this->validateWebhook;
    }

    private function getRecordWebhookLog(): RecordWebhookLog
    {
        if ($this->recordWebhookLog === null) {
            $this->recordWebhookLog = app(RecordWebhookLog::class);
        }

        return $this->recordWebhookLog;
    }

    private function getMetadataResolver(): WebhookServiceMetadataResolver
    {
        if ($this->metadataResolver === null) {
            $this->metadataResolver = app(WebhookServiceMetadataResolver::class);
        }

        return $this->metadataResolver;
    }

    private function getPayloadParserResolver(): WebhookPayloadParserResolver
    {
        if ($this->payloadParserResolver === null) {
            $this->payloadParserResolver = app(WebhookPayloadParserResolver::class);
        }

        return $this->payloadParserResolver;
    }

    private function extractEvent(WebhookService $service, RawPayload $payload): string
    {
        return $this->getPayloadParserResolver()
            ->forService($service)
            ->extractEventType($payload->decoded());
    }

    private function extractExternalId(WebhookService $service, RawPayload $payload): ?string
    {
        return $this->getPayloadParserResolver()
            ->forService($service)
            ->extractExternalId($payload->decoded());
    }

    /**
     * @throws WebhookException
     */
    private function validateAndRecord(
        string $payload,
        Signature $signature,
        string|WebhookService $service,
        string $event,
        int $attempt,
        ?string $externalId = null,
    ): WebhookLog {
        $webhookService = $this->resolveService($service);
        $rawPayload = RawPayload::fromString($payload);
        $secret = $this->getMetadataResolver()->secret($webhookService);

        if ($secret === null || $secret === '') {
            throw new WebhookException("No secret configured for service: {$webhookService->value}");
        }

        $resolvedExternalId = $externalId ?? $this->extractExternalId($webhookService, $rawPayload);

        $validation = $this->getValidateWebhook()->handle(new ValidateWebhookCommand(
            service: $webhookService,
            payload: $rawPayload,
            signature: $signature,
            event: $event,
            externalId: $resolvedExternalId,
            secret: $secret,
        ));

        return $this->getRecordWebhookLog()->handle(new RecordWebhookLogCommand(
            service: $validation->service,
            event: $validation->event,
            valid: $validation->isValid(),
            payload: $validation->payload,
            attempt: $attempt,
            externalId: $validation->externalId,
            errorMessage: $validation->errorMessage,
        ));
    }
}
