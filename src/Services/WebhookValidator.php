<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Services;

use Exception;
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
     * Validates a webhook signature.
     *
     * @param  string  $payload  Raw webhook content
     * @param  string  $signature  Signature provided by the service
     * @param  string  $service  Service name ('stripe' or 'github')
     *
     * @throws InvalidSignatureException
     * @throws WebhookException
     */
    public function validate(string $payload, string $signature, string $service): bool
    {
        match ($service) {
            'stripe' => $this->validateStripeSignature($payload, $signature),
            'github' => $this->validateGithubSignature($payload, $signature),
            default => throw new WebhookException("Unsupported service: {$service}"),
        };

        return true;
    }

    /**
     * Validate Stripe webhook signature.
     *
     * Stripe signature format: "t=1671234567,v1=abc123..."
     *
     *
     *
     * @throws InvalidSignatureException
     * @throws WebhookException
     */
    private function validateStripeSignature(string $payload, string $signatureHeader): string
    {
        // Extract timestamp and signature from header
        preg_match('/t=(\d+)/', $signatureHeader, $timestampMatch);
        preg_match('/v1=([a-f0-9]+)/', $signatureHeader, $signatureMatch);

        if (empty($timestampMatch[1]) || empty($signatureMatch[1])) {
            throw new WebhookException('Invalid Stripe signature format.');
        }

        $timestamp = (int) $timestampMatch[1];
        $providedSignature = $signatureMatch[1];

        // Check timestamp tolerance
        if ($this->isExpired($timestamp)) {
            throw new WebhookException("Webhook is expired (tolerance: {$this->tolerance}s).");
        }

        // Compute expected signature
        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $this->secret);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            throw new InvalidSignatureException('Invalid Stripe webhook signature.');
        }

        return $signatureHeader;
    }

    /**
     * Validates webhook signature and logs the result.
     *
     * @param  string  $payload  Raw webhook content
     * @param  string  $signature  Signature provided by the service
     * @param  string  $service  Service name ('stripe' or 'github')
     * @param  string  $event  Event type (e.g., 'payment_intent.succeeded')
     * @return WebhookLog The created log entry
     *
     * @throws Exception
     */
    public function validateAndLog(
        string $payload,
        string $signature,
        string $service,
        string $event,
        int $attempt = 0
    ): WebhookLog {
        $logger = $this->logger ?? new WebhookLogger;
        $decodedPayload = json_decode($payload, true) ?? ['raw' => $payload];

        try {
            $this->validate($payload, $signature, $service);

            return $logger->logSuccess($service, $event, $decodedPayload, $attempt);
        } catch (WebhookException|InvalidSignatureException $e) {
            return $logger->logFailure($service, $event, $decodedPayload, $e->getMessage(), $attempt);
        }
    }

    /**
     * Validates GitHub webhook signature.
     *
     * GitHub signature format: "sha256=abc123..."
     *
     *
     *
     * @throws InvalidSignatureException
     */
    private function validateGithubSignature(string $payload, string $signatureHeader): string
    {
        // GitHub format: sha256=hash
        if (! str_starts_with($signatureHeader, 'sha256=')) {
            throw new InvalidSignatureException('Invalid GitHub signature format.');
        }

        $providedSignature = substr($signatureHeader, 7); // Remove 'sha256=' prefix
        $expectedSignature = hash_hmac('sha256', $payload, $this->secret);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            throw new InvalidSignatureException('Invalid GitHub webhook signature.');
        }

        return $signatureHeader;
    }

    /**
     * Validates webhook signature with automatic retries on failure.
     *
     * @param  string  $payload  Raw webhook content
     * @param  string  $signature  Signature provided by the service
     * @param  string  $service  Service name ('stripe' or 'github')
     * @param  string  $event  Event type (e.g., 'payment_intent.succeeded')
     * @return WebhookLog The final log entry (success or last failed attempt)
     *
     * @throws WebhookException|InvalidSignatureException If all retries fail
     * @throws Exception
     */
    public function validateWithRetries(
        string $payload,
        string $signature,
        string $service,
        string $event
    ): WebhookLog {
        if (! config('larawebhook.retries.enabled', true)) {
            return $this->validateAndLog($payload, $signature, $service, $event);
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
                return $logger->logSuccess($service, $event, $decodedPayload, $attempt);
            } catch (WebhookException|InvalidSignatureException $e) {
                $lastException = $e;

                // Log the failure
                $logger->logFailure(
                    $service,
                    $event,
                    $decodedPayload,
                    $e->getMessage(),
                    $attempt
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

    private function isExpired(int $timestamp): bool
    {
        return (time() - $timestamp) > $this->tolerance;
    }
}
