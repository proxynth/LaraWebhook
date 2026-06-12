<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Results;

use Carbon\CarbonInterface;
use Proxynth\Larawebhook\Shared\Application\Results\Result;

final readonly class PruneWebhookLogsResult implements Result
{
    public const STATUS_DISABLED = 'disabled';

    public const STATUS_DRY_RUN = 'dry_run';

    public const STATUS_DELETED = 'deleted';

    private function __construct(
        public string $status,
        public int $count,
        public ?CarbonInterface $cutoff,
    ) {}

    public static function disabled(): self
    {
        return new self(self::STATUS_DISABLED, 0, null);
    }

    public static function dryRun(int $count, CarbonInterface $cutoff): self
    {
        return new self(self::STATUS_DRY_RUN, $count, $cutoff);
    }

    public static function deleted(int $count, CarbonInterface $cutoff): self
    {
        return new self(self::STATUS_DELETED, $count, $cutoff);
    }

    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    public function isDryRun(): bool
    {
        return $this->status === self::STATUS_DRY_RUN;
    }

    public function isDeleted(): bool
    {
        return $this->status === self::STATUS_DELETED;
    }
}
