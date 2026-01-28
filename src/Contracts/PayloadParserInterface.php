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
     * Get the service name this parser handles.
     */
    public function serviceName(): string;
}
