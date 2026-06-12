<?php

namespace Proxynth\Larawebhook\Audit\Domain\Enum;

enum PayloadStorageMode: string
{
    case None = 'none';
    case Redacted = 'redacted';
    case Full = 'full';

    public static function fromConfig(?string $mode = null): self
    {
        return match ($mode) {
            self::None->value => self::None,
            self::Redacted->value => self::Redacted,
            self::Full->value => self::Full,
            default => self::Redacted,
        };
    }
}
