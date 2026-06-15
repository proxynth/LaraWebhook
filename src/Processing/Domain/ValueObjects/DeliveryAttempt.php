<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class DeliveryAttempt
{
    private function __construct(
        private int $value,
    ) {}

    public static function initial(): self
    {
        return new self(0);
    }

    public static function fromInt(int $value): self
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Delivery attempt cannot be negative.');
        }

        return new self($value);
    }

    public function next(): self
    {
        return new self($this->value + 1);
    }

    public function value(): int
    {
        return $this->value;
    }
}
