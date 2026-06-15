<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Domain\ValueObjects;

enum PayloadStorageMode: string
{
    case None = 'none';
    case Redacted = 'redacted';
    case Full = 'full';

    public static function fromConfig(mixed $value): self
    {
        if (! is_string($value)) {
            throw new \InvalidArgumentException('Payload storage mode must be a string.');
        }

        return self::tryFrom($value) ?? throw new \InvalidArgumentException("Invalid payload storage mode [$value].");
    }

    public function storesPayload(): bool
    {
        return $this !== self::None;
    }

    public function storesFullPayload(): bool
    {
        return $this === self::Full;
    }

    public function storesRedactedPayload(): bool
    {
        return $this === self::Redacted;
    }
}
