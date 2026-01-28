<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Validators;

use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;

/**
 * Validator for Stripe webhook signatures.
 *
 * Stripe signature format: "t=1671234567,v1=abc123..."
 *
 * @see https://stripe.com/docs/webhooks/signatures
 */
class StripeSignatureValidator implements SignatureValidatorInterface
{
    /**
     * Validate Stripe webhook signature.
     *
     * @throws InvalidSignatureException If the signature doesn't match
     * @throws WebhookException If the format is invalid or timestamp expired
     */
    public function validate(string $payload, string $signature, string $secret, int $tolerance = 300): bool
    {
        // Extract timestamp and signature from header
        preg_match('/t=(\d+)/', $signature, $timestampMatch);
        preg_match('/v1=([a-f0-9]+)/', $signature, $signatureMatch);

        if (empty($timestampMatch[1]) || empty($signatureMatch[1])) {
            throw new WebhookException('Invalid Stripe signature format.');
        }

        $timestamp = (int) $timestampMatch[1];
        $providedSignature = $signatureMatch[1];

        // Check timestamp tolerance
        if ($this->isExpired($timestamp, $tolerance)) {
            throw new WebhookException("Webhook is expired (tolerance: {$tolerance}s).");
        }

        // Compute expected signature
        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            throw new InvalidSignatureException('Invalid Stripe webhook signature.');
        }

        return true;
    }

    /**
     * Get the service name this validator handles.
     */
    public function serviceName(): string
    {
        return 'stripe';
    }

    /**
     * Check if the timestamp is expired.
     */
    private function isExpired(int $timestamp, int $tolerance): bool
    {
        return (time() - $timestamp) > $tolerance;
    }
}
