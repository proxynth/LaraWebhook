<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Services;

use Exception;
use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Models\WebhookLog;

class WebhookValidator
{
    public function __construct(
        private readonly string $secret,
        private readonly int $tolerance = 300,
        private readonly ?WebhookLogger $logger = null
    ) {}

    /**
     * Validates a webhook signature using the service's validator.
     *
     * @param  string  $payload  Raw webhook content
     * @param  string  $signature  Signature provided by the service
     * @param  string|WebhookService  $service  Service name or enum
     *
     * @throws InvalidSignatureException
     * @throws WebhookException
     */
    public function validate(string $payload, string $signature, string|WebhookService $service): bool
    {
        $webhookService = $service instanceof WebhookService
            ? $service
            : WebhookService::tryFromString($service);

        if ($webhookService === null) {
            throw new WebhookException("Unsupported service: {$service}");
        }

        return $webhookService->signatureValidator()->validate(
            $payload,
            $signature,
            $this->secret,
            $this->tolerance
        );
    }

    /**
     * Validates webhook signature and logs the result.
     *
     * @param  string  $payload  Raw webhook content
     * @param  string  $signature  Signature provided by the service
     * @param  string|WebhookService  $service  Service name or enum
     * @param  string  $event  Event type (e.g., 'payment_intent.succeeded')
     * @param  int  $attempt  Retry attempt number (0 = first try)
     * @param  string|null  $externalId  External ID for idempotency
     * @return WebhookLog The created log entry
     *
     * @throws Exception
     */
    public function validateAndLog(
        string $payload,
        string $signature,
        string|WebhookService $service,
        string $event,
        int $attempt = 0,
        ?string $externalId = null
    ): WebhookLog {
        $serviceName = $service instanceof WebhookService ? $service->value : $service;
        $logger = $this->logger ?? new WebhookLogger;
        $decodedPayload = json_decode($payload, true) ?? ['raw' => $payload];

        try {
            $this->validate($payload, $signature, $service);

            return $logger->logSuccess($serviceName, $event, $decodedPayload, $attempt, $externalId);
        } catch (WebhookException|InvalidSignatureException $e) {
            return $logger->logFailure($serviceName, $event, $decodedPayload, $e->getMessage(), $attempt, $externalId);
        }
    }

    /**
     * Validates webhook signature with automatic retries on failure.
     *
     * @param  string  $payload  Raw webhook content
     * @param  string  $signature  Signature provided by the service
     * @param  string|WebhookService  $service  Service name or enum
     * @param  string  $event  Event type (e.g., 'payment_intent.succeeded')
     * @param  string|null  $externalId  External ID for idempotency
     * @return WebhookLog The final log entry (success or last failed attempt)
     *
     * @throws WebhookException|InvalidSignatureException If all retries fail
     * @throws Exception
     */
    public function validateWithRetries(
        string $payload,
        string $signature,
        string|WebhookService $service,
        string $event,
        ?string $externalId = null
    ): WebhookLog {
        $serviceName = $service instanceof WebhookService ? $service->value : $service;

        if (! config('larawebhook.retries.enabled', true)) {
            return $this->validateAndLog($payload, $signature, $service, $event, 0, $externalId);
        }

        $logger = $this->logger ?? new WebhookLogger;
        $decodedPayload = json_decode($payload, true) ?? ['raw' => $payload];
        $maxAttempts = config('larawebhook.retries.max_attempts', 3);
        $delays = config('larawebhook.retries.delays', [1, 5, 10]);

        $lastException = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $this->validate($payload, $signature, $service);

                // Success - log and return
                return $logger->logSuccess($serviceName, $event, $decodedPayload, $attempt, $externalId);
            } catch (WebhookException|InvalidSignatureException $e) {
                $lastException = $e;

                // Log the failure
                $logger->logFailure(
                    $serviceName,
                    $event,
                    $decodedPayload,
                    $e->getMessage(),
                    $attempt,
                    $externalId
                );

                // If not the last attempt, wait before retrying
                if ($attempt < $maxAttempts - 1 && isset($delays[$attempt])) {
                    sleep($delays[$attempt]);
                }
            }
        }

        // All retries failed - throw the last exception
        if ($lastException !== null) {
            throw $lastException;
        }

        // This should never happen, but ensures type safety
        throw new WebhookException('Validation failed with no recorded exception.');
    }
}
