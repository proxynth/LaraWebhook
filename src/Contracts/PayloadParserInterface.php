<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Contracts;

/**
 * Interface for parsing webhook payloads.
 *
 * Each webhook service (Stripe, GitHub, etc.) has its own payload structure.
 * Implementations of this interface extract relevant data in a standardized way.
 */
interface PayloadParserInterface
{
    /**
     * Extract the event type from the payload.
     *
     * @param  array<string, mixed>  $data  The decoded payload data
     * @return string The event type identifier
     */
    public function extractEventType(array $data): string;

    /**
     * Extract metadata from the payload.
     *
     * @param  array<string, mixed>  $data  The decoded payload data
     * @return array<string, mixed> Relevant metadata for logging/processing
     */
    public function extractMetadata(array $data): array;

    /**
     * Extract the external ID from the payload for idempotency checking.
     *
     * The external ID is a unique identifier provided by the webhook source
     * (e.g., Stripe's event ID, GitHub's delivery ID) used to detect
     * duplicate webhook deliveries.
     *
     * @param  array<string, mixed>  $data  The decoded payload data
     * @param  string|null  $headerValue  The external ID from headers (if applicable)
     * @return string|null The external ID or null if not available
     */
    public function extractExternalId(array $data, ?string $headerValue = null): ?string;

    /**
     * Get the service name this parser handles.
     */
    public function serviceName(): string;
}
