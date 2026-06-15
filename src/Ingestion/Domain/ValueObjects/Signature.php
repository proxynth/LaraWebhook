<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class Signature implements \Stringable
{
    private function __construct(
        private string $value,
        private ?string $timestamp = null,
    ) {}

    public static function fromString(string $value, ?string $timestamp = null): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Signature cannot be empty.');
        }

        if ($timestamp !== null) {
            $timestamp = trim($timestamp);

            if ($timestamp === '') {
                $timestamp = null;
            }
        }

        return new self($value, $timestamp);
    }

    public static function optional(?string $value, ?string $timestamp = null): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::fromString($value, $timestamp);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function timestamp(): ?string
    {
        return $this->timestamp;
    }

    public function requiresTimestamp(): bool
    {
        return $this->timestamp !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
