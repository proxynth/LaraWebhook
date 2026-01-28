<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Contracts;

use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;

/**
 * Interface for validating webhook signatures.
 *
 * Each webhook service (Stripe, GitHub, etc.) has its own signature format.
 * Implementations of this interface handle the specific validation logic.
 */
interface SignatureValidatorInterface
{
    /**
     * Validate the webhook signature.
     *
     * @param  string  $payload  The raw webhook payload
     * @param  string  $signature  The signature header value
     * @param  string  $secret  The webhook secret
     * @param  int  $tolerance  Timestamp tolerance in seconds (for services that use timestamps)
     * @return bool True if valid
     *
     * @throws InvalidSignatureException If the signature is invalid
     * @throws WebhookException If there's a validation error (format, expired, etc.)
     */
    public function validate(string $payload, string $signature, string $secret, int $tolerance = 300): bool;

    /**
     * Get the service name this validator handles.
     */
    public function serviceName(): string;
}
