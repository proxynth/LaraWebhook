<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Domain\ValueObjects;

final readonly class StoredPayload
{
    public function __construct(
        private ?array $value,
    ) {}

    public static function full(array $payload): self
    {
        return new self($payload);
    }

    public static function redacted(array $payload): self
    {
        return new self($payload);
    }

    public static function none(): self
    {
        return new self(null);
    }

    public function value(): ?array
    {
        return $this->value;
    }

    public function isStored(): bool
    {
        return $this->value !== null;
    }
}
