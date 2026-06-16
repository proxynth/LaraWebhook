<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Domain\ValueObjects;

use InvalidArgumentException;
use Stringable;

final readonly class WebhookStatus implements Stringable
{
    public const RECEIVED = 'received';

    public const VALIDATED = 'validated';

    public const PROCESSING = 'processing';

    public const PROCESSED = 'processed';

    public const FAILED = 'failed';

    public const REPLAYED = 'replayed';

    /**
     * Backward-compatible audit statuses.
     */
    public const SUCCESS = 'success';

    private const ALLOWED = [
        self::RECEIVED,
        self::VALIDATED,
        self::PROCESSING,
        self::PROCESSED,
        self::FAILED,
        self::REPLAYED,
        self::SUCCESS,
    ];

    private const TERMINAL = [
        self::PROCESSED,
        self::FAILED,
    ];

    private function __construct(
        private string $value,
    ) {}

    public static function received(): self
    {
        return new self(self::RECEIVED);
    }

    public static function validated(): self
    {
        return new self(self::VALIDATED);
    }

    public static function processing(): self
    {
        return new self(self::PROCESSING);
    }

    public static function processed(): self
    {
        return new self(self::PROCESSED);
    }

    /**
     * Kept for existing audit-oriented code/tests.
     */
    public static function success(): self
    {
        return new self(self::SUCCESS);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public static function replayed(): self
    {
        return new self(self::REPLAYED);
    }

    public static function fromString(string $value): self
    {
        if (! in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException("Invalid webhook status [{$value}].");
        }

        return new self($value);
    }

    public function isReceived(): bool
    {
        return $this->value === self::RECEIVED;
    }

    public function isValidated(): bool
    {
        return $this->value === self::VALIDATED;
    }

    public function isProcessing(): bool
    {
        return $this->value === self::PROCESSING;
    }

    public function isProcessed(): bool
    {
        return $this->value === self::PROCESSED;
    }

    public function isSuccess(): bool
    {
        return $this->value === self::SUCCESS || $this->value === self::PROCESSED;
    }

    public function isFailed(): bool
    {
        return $this->value === self::FAILED;
    }

    public function isReplayed(): bool
    {
        return $this->value === self::REPLAYED;
    }

    public function isTerminal(): bool
    {
        return in_array($this->value, self::TERMINAL, true);
    }

    public function canBeReplayed(): bool
    {
        return $this->isFailed() || $this->isProcessed() || $this->value === self::SUCCESS;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $status): bool
    {
        return $this->value === $status->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
