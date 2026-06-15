<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Validation;

use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Application\Data\IncomingWebhookSignature;

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
     * @param  IncomingWebhookSignature  $signature  The incoming Slack signature data
     * @param  string  $secret  The Slack signing secret
     * @param  int  $tolerance  Timestamp tolerance in seconds
     *
     * @throws InvalidSignatureException If the signature doesn't match
     * @throws WebhookException If the format is invalid or timestamp expired
     */
    public function validate(
        string $payload,
        IncomingWebhookSignature $signature,
        string $secret,
        int $tolerance = 300,
    ): bool {
        if ($signature->timestamp === null || $signature->timestamp === '') {
            throw new WebhookException('Missing Slack request timestamp.');
        }

        if (! is_numeric($signature->timestamp)) {
            throw new WebhookException('Invalid Slack request timestamp.');
        }

        $timestamp = (int) $signature->timestamp;
        $providedSignature = $signature->value;

        if ($this->isExpired($timestamp, $tolerance)) {
            throw new WebhookException("Webhook is expired (tolerance: {$tolerance}s).");
        }

        if (! str_starts_with($providedSignature, 'v0=')) {
            throw new InvalidSignatureException('Invalid Slack signature format. Expected "v0=" prefix.');
        }

        $sigBaseString = "v0:{$timestamp}:{$payload}";
        $expectedSignature = 'v0='.hash_hmac('sha256', $sigBaseString, $secret);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            throw new InvalidSignatureException('Invalid Slack webhook signature.');
        }

        return true;
    }

    public function serviceName(): string
    {
        return 'slack';
    }

    private function isExpired(int $timestamp, int $tolerance): bool
    {
        return abs(time() - $timestamp) > $tolerance;
    }
}
