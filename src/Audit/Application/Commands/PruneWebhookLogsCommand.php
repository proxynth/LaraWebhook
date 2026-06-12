<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Commands;

use Carbon\CarbonInterface;
use Proxynth\Larawebhook\Shared\Application\Commands\Command;

final readonly class PruneWebhookLogsCommand implements Command
{
    public function __construct(
        public bool $retentionEnabled,
        public CarbonInterface $cutoff,
        public bool $dryRun = false,
    ) {}
}
