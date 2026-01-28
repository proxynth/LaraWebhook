<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Validators;

use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;

/**
 * Validator for GitHub webhook signatures.
 *
 * GitHub signature format: "sha256=abc123..."
 *
 * @see https://docs.github.com/en/webhooks/using-webhooks/validating-webhook-deliveries
 */
class GithubSignatureValidator implements SignatureValidatorInterface
{
    /**
     * Validate GitHub webhook signature.
     *
     * @throws InvalidSignatureException If the signature is invalid or format is wrong
     */
    public function validate(string $payload, string $signature, string $secret, int $tolerance = 300): bool
    {
        // GitHub format: sha256=hash
        if (! str_starts_with($signature, 'sha256=')) {
            throw new InvalidSignatureException('Invalid GitHub signature format.');
        }

        $providedSignature = substr($signature, 7); // Remove 'sha256=' prefix
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            throw new InvalidSignatureException('Invalid GitHub webhook signature.');
        }

        return true;
    }

    /**
     * Get the service name this validator handles.
     */
    public function serviceName(): string
    {
        return 'github';
    }
}
