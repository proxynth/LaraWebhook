<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Parsers;

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;

/**
 * Parser for Stripe webhook payloads.
 *
 * @see https://stripe.com/docs/webhooks
 */
class StripePayloadParser implements PayloadParserInterface
{
    /**
     * Extract the event type from a Stripe webhook payload.
     *
     * Stripe uses a "type" field like "payment_intent.succeeded", "charge.failed", etc.
     *
     * @param  array<string, mixed>  $data
     */
    public function extractEventType(array $data): string
    {
        return $data['type'] ?? 'unknown';
    }

    /**
     * Extract relevant metadata from a Stripe webhook payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function extractMetadata(array $data): array
    {
        return [
            'event_id' => $data['id'] ?? null,
            'api_version' => $data['api_version'] ?? null,
            'livemode' => $data['livemode'] ?? null,
            'object_id' => $data['data']['object']['id'] ?? null,
            'object_type' => $data['data']['object']['object'] ?? null,
        ];
    }

    /**
     * Get the service name this parser handles.
     */
    public function serviceName(): string
    {
        return 'stripe';
    }
}
