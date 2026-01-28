<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Validators;

use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;

/**
 * Validator for Slack webhook signatures.
 *
 * Slack signature format: "v0=hash"
 * The signature is computed as: v0:timestamp:body
 *
 * Headers:
 * - X-Slack-Signature: v0=abc123...
 * - X-Slack-Request-Timestamp: 1531420618
 *
 * @see https://api.slack.com/authentication/verifying-requests-from-slack
 */
class SlackSignatureValidator implements SignatureValidatorInterface
{
    /**
     * Validate Slack webhook signature.
     *
     * The signature format includes timestamp in the signed payload:
     * signature = 'v0=' + HMAC-SHA256(signing_secret, 'v0:' + timestamp + ':' + body)
     *
     * @param  string  $payload  The raw request body
     * @param  string  $signature  The X-Slack-Signature header (format: "v0=timestamp:signature")
     * @param  string  $secret  The Slack signing secret
     * @param  int  $tolerance  Timestamp tolerance in seconds
     *
     * @throws InvalidSignatureException If the signature doesn't match
     * @throws WebhookException If the format is invalid or timestamp expired
     */
    public function validate(string $payload, string $signature, string $secret, int $tolerance = 300): bool
    {
        // Parse the signature - format: "v0=hash" or "v0:timestamp:v0=hash"
        // We need to extract timestamp from the signature string if combined
        // Standard format from Slack is separate headers, but we combine them as: "timestamp:v0=hash"
        $parts = explode(':', $signature, 2);

        if (count($parts) === 2 && is_numeric($parts[0])) {
            // Combined format: "timestamp:v0=hash"
            $timestamp = (int) $parts[0];
            $providedSignature = $parts[1];
        } else {
            // Signature only - timestamp should be 0 (for testing) or error
            throw new WebhookException('Invalid Slack signature format. Expected "timestamp:v0=signature".');
        }

        // Check timestamp tolerance
        if ($this->isExpired($timestamp, $tolerance)) {
            throw new WebhookException("Webhook is expired (tolerance: {$tolerance}s).");
        }

        // Validate v0= prefix
        if (! str_starts_with($providedSignature, 'v0=')) {
            throw new InvalidSignatureException('Invalid Slack signature format. Expected "v0=" prefix.');
        }

        // Compute expected signature
        // Slack format: v0:timestamp:body
        $sigBaseString = "v0:{$timestamp}:{$payload}";
        $expectedSignature = 'v0='.hash_hmac('sha256', $sigBaseString, $secret);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            throw new InvalidSignatureException('Invalid Slack webhook signature.');
        }

        return true;
    }

    /**
     * Get the service name this validator handles.
     */
    public function serviceName(): string
    {
        return 'slack';
    }

    /**
     * Check if the timestamp is expired.
     */
    private function isExpired(int $timestamp, int $tolerance): bool
    {
        return abs(time() - $timestamp) > $tolerance;
    }
}
