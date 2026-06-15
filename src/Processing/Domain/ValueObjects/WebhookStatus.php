<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Domain\ValueObjects;

use InvalidArgumentException;
use Stringable;

final readonly class WebhookStatus implements Stringable
{
    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    private const ALLOWED = [
        self::SUCCESS,
        self::FAILED,
    ];

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
        if (! in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException("Invalid webhook status [{$value}].");
        }

        return new self($value);
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
