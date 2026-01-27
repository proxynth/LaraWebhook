<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Services;

use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;

class WebhookValidator
{
    public function __construct(
        private readonly string $secret,
        private readonly int $tolerance = 300
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
        $expectedSignature = match ($service) {
            'stripe' => $this->validateStripeSignature($payload, $signature),
            'github' => $this->validateGithubSignature($payload, $signature),
            default => throw new WebhookException("Unsupported service: {$service}"),
        };

        if (! hash_equals($expectedSignature, $signature)) {
            throw new InvalidSignatureException("Invalid signature for {$service} webhook.");
        }

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

    private function isExpired(int $timestamp): bool
    {
        return (time() - $timestamp) > $this->tolerance;
    }
}
