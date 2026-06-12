<?php

namespace Proxynth\Larawebhook\Processing\Infrastructure\Idempotency;

use JsonException;
use Proxynth\Larawebhook\Processing\Application\Ports\IdempotencyResolver;

class DefaultIdempotencyResolver implements IdempotencyResolver
{
    /**
     * {@inheritDoc}
     */
    public function resolve(string $service, ?array $payload, ?string $externalId, ?string $event): ?string
    {
        if (! empty($externalId)) {
            return $externalId;
        }

        return $this->hashPayload($payload);
    }

    /**
     * @throws JsonException
     */
    private function hashPayload(?array $payload): string
    {
        $normalizedPayload = $this->normalizePayload($payload);

        return 'payload_hash:'.hash(
            'sha256',
            json_encode($normalizedPayload, JSON_THROW_ON_ERROR)
        );
    }

    private function normalizePayload(mixed $value)
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->normalizePayload($item),
                $value
            );
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizePayload($item);
        }

        return $value;
    }
}
