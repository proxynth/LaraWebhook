<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Domain\ValueObjects;

class EventType implements \Stringable
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Event type cannot be empty.');
        }

        return new self($value);
    }

    public static function unknown(): self
    {
        return new self('unknown');
    }

    public function isUnknown(): bool
    {
        return $this->value === 'unknown';
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
