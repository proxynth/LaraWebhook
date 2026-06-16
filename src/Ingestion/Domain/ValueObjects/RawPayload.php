<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Domain\ValueObjects;

use JsonException;
use Stringable;

class RawPayload implements Stringable
{
    public function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        return $value === '' ? throw new \InvalidArgumentException('Raw payload cannot be empty.') : new self($value);
    }

    /**
     * @throws JsonException
     */
    public static function fromArray(array $payload): self
    {
        return new self(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function decoded(): array
    {
        $decoded = json_decode($this->value, true);

        return is_array($decoded)
            ? $decoded
            : ['raw' => $this->value];
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return $this->value;
    }
}
