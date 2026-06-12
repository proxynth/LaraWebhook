<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\UseCases;

use Proxynth\Larawebhook\Audit\Application\Commands\PruneWebhookLogsCommand;
use Proxynth\Larawebhook\Audit\Application\Results\PruneWebhookLogsResult;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

final readonly class PruneWebhookLogs
{
    public function handle(PruneWebhookLogsCommand $command): PruneWebhookLogsResult
    {
        if (! $command->retentionEnabled) {
            return PruneWebhookLogsResult::disabled();
        }

        $query = WebhookLog::query()
            ->where('created_at', '<', $command->cutoff);

        $count = $query->count();

        if ($command->dryRun) {
            return PruneWebhookLogsResult::dryRun($count, $command->cutoff);
        }

        $deleted = $query->delete();

        return PruneWebhookLogsResult::deleted($deleted, $command->cutoff);
    }
}
