<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Domain\ValueObjects;

use InvalidArgumentException;
use Stringable;

final readonly class Provider implements Stringable
{
    public function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $value = trim($value);

        return $value === '' ? throw new InvalidArgumentException('Provider cannot be empty.') : new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $provider): bool
    {
        return $this->value === $provider->value();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
