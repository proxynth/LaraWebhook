<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Domain\ValueObjects;

use InvalidArgumentException;
use Stringable;

final readonly class IdempotencyKey implements Stringable
{
    public function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $value = trim($value);

        return $value === '' ? throw new InvalidArgumentException('Idempotency key cannot be empty.') : new self($value);
    }

    public static function optional(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::fromString($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
