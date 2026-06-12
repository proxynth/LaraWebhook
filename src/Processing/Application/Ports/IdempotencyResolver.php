<?php

namespace Proxynth\Larawebhook\Processing\Application\Ports;

interface IdempotencyResolver
{
    /**
     * Resolve a stable idempotency key for a webhook event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function resolve(
        string $service,
        ?array $payload,
        ?string $externalId,
        ?string $event
    ): ?string;
}
