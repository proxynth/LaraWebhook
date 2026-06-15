<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Domain\ValueObjects;

use Stringable;

final readonly class WebhookStatus implements Stringable
{
    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    public function __construct(
        private string $value,
    ) {}

    public static function success(): self
    {
        return new self(self::SUCCESS);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public static function fromString(string $value): self
    {
        return match ($value) {
            self::SUCCESS => self::success(),
            self::FAILED => self::failed(),
            default => throw new \InvalidArgumentException("Invalid webhook status: [$value]."),
        };
    }

    public function isSuccess(): bool
    {
        return $this->value === self::SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->value === self::FAILED;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
