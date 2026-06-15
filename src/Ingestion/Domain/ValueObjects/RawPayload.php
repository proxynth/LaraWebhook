<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Domain\ValueObjects;

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

    public function value(): string
    {
        return $this->value;
    }

    public function decoded(): array
    {
        $decoded = json_decode($this->value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return $this->value;
    }
}
