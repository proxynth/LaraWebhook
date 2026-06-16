<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\UseCases;

use Proxynth\Larawebhook\Audit\Application\Commands\PruneWebhookLogsCommand;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookLogRepository;
use Proxynth\Larawebhook\Audit\Application\Results\PruneWebhookLogsResult;

final readonly class PruneWebhookLogs
{
    public function __construct(
        private WebhookLogRepository $webhookLogsRepository,
    ) {}

    public function handle(PruneWebhookLogsCommand $command): PruneWebhookLogsResult
    {
        if (! $command->retentionEnabled) {
            return PruneWebhookLogsResult::disabled();
        }

        $count = $this->webhookLogsRepository->countOlderThan($command->cutoff);

        if ($command->dryRun) {
            return PruneWebhookLogsResult::dryRun($count, $command->cutoff);
        }

        $deleted = $this->webhookLogsRepository->deleteOlderThan($command->cutoff);

        return PruneWebhookLogsResult::deleted($deleted, $command->cutoff);
    }
}
