<?php

namespace Proxynth\Larawebhook\Services;

use Proxynth\Larawebhook\Contracts\IdempotencyResolver;

class DefaultIdempotencyResolver implements IdempotencyResolver
{
    /**
     * {@inheritDoc}
     */
    public function resolve(string $service, ?array $payload, ?string $externalId, ?string $event): ?string
    {
        return $externalId;
    }
}
