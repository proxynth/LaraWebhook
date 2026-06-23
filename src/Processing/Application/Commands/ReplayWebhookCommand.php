<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Commands;

use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Shared\Application\Commands\Command;

final readonly class ReplayWebhookCommand implements Command
{
    public function __construct(
        public int|string $webhookLogId,
        public Signature $signature,
    ) {}
}
