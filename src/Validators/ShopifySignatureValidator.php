<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Validators;

use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;

/**
 * Validator for Shopify webhook signatures.
 *
 * Shopify signature format: Base64-encoded HMAC-SHA256
 * Header: X-Shopify-Hmac-Sha256
 *
 * @see https://shopify.dev/docs/apps/webhooks/configuration/https#verify-the-webhook
 */
class ShopifySignatureValidator implements SignatureValidatorInterface
{
    /**
     * Validate Shopify webhook signature.
     *
     * Shopify uses Base64-encoded HMAC-SHA256:
     * signature = Base64(HMAC-SHA256(shared_secret, body))
     *
     * @param  string  $payload  The raw request body
     * @param  string  $signature  The X-Shopify-Hmac-Sha256 header (Base64 encoded)
     * @param  string  $secret  The Shopify webhook secret
     * @param  int  $tolerance  Not used for Shopify (no timestamp validation)
     *
     * @throws InvalidSignatureException If the signature doesn't match
     */
    public function validate(string $payload, string $signature, string $secret, int $tolerance = 300): bool
    {
        if (empty($signature)) {
            throw new InvalidSignatureException('Missing Shopify signature.');
        }

        // Shopify uses Base64-encoded HMAC-SHA256
        $expectedSignature = base64_encode(
            hash_hmac('sha256', $payload, $secret, true)
        );

        if (! hash_equals($expectedSignature, $signature)) {
            throw new InvalidSignatureException('Invalid Shopify webhook signature.');
        }

        return true;
    }

    /**
     * Get the service name this validator handles.
     */
    public function serviceName(): string
    {
        return 'shopify';
    }
}
