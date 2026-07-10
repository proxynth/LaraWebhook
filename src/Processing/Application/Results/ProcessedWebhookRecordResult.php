<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Results;

final readonly class ProcessedWebhookRecordResult
{
    private function __construct(
        public bool $recorded,
        public bool $alreadyRecorded,
    ) {}

    public static function recorded(): self
    {
        return new self(true, false);
    }

    public static function alreadyRecorded(): self
    {
        return new self(false, true);
    }
}
