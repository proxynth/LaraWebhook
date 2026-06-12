<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Proxynth\Larawebhook\Audit\Application\Commands\PruneWebhookLogsCommand;
use Proxynth\Larawebhook\Audit\Application\Results\PruneWebhookLogsResult;
use Proxynth\Larawebhook\Audit\Application\UseCases\PruneWebhookLogs;

/**
 * Command to clean up old webhook logs.
 *
 * Usage:
 *   php artisan larawebhook:cleanup         # Delete logs older than 30 days
 *   php artisan larawebhook:cleanup --days=7  # Delete logs older than 7 days
 *   php artisan larawebhook:cleanup --status=failed  # Delete only failed logs
 *   php artisan larawebhook:cleanup --dry-run  # Show what would be deleted
 */
class PruneWebhookLogsConsoleCommand extends Command
{
    public $signature = 'larawebhook:prune
        {--older-than= : Delete logs older than this duration, for example 7d, 30d, 12h}
        {--dry-run : Show how many logs would be deleted without deleting them}';

    protected $description = 'Prune old LaraWebhook logs according to the configured retention policy.';

    public function __construct(
        private readonly PruneWebhookLogs $pruneWebhookLogs,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cutoff = $this->resolveCutoff();

        if (! $cutoff) {
            $this->error('Invalid --older-than value. Use a duration like 7d, 30d, 12h, or 60m.');

            return self::FAILURE;
        }

        $result = $this->pruneWebhookLogs->handle(new PruneWebhookLogsCommand(
            retentionEnabled: (bool) config('larawebhook.retention.enabled', true),
            cutoff: $cutoff,
            dryRun: (bool) $this->option('dry-run'),
        ));

        $this->renderResult($result);

        return self::SUCCESS;
    }

    private function resolveCutoff(): ?Carbon
    {
        $olderThan = $this->option('older-than');

        if ($olderThan === null || $olderThan === '') {
            return now()->subDays((int) config('larawebhook.retention.days', 30));
        }

        $olderThan = (string) $olderThan;

        if (! preg_match('/^(\d+)([dhm])$/', $olderThan, $matches)) {
            return null;
        }

        $amount = (int) $matches[1];

        if ($amount <= 0) {
            return null;
        }

        $unit = $matches[2];
        if ($unit === 'd') {
            return now()->subDays($amount);
        }

        if ($unit === 'h') {
            return now()->subHours($amount);
        }

        return now()->subMinutes($amount);
    }

    private function renderResult(PruneWebhookLogsResult $result): void
    {
        if ($result->isDisabled()) {
            $this->warn('LaraWebhook retention is disabled. No logs were pruned.');

            return;
        }

        if (! $result->cutoff) {
            $this->error('Unable to determine pruning cutoff.');

            return;
        }

        if ($result->isDryRun()) {
            $this->info(sprintf(
                'Dry run: %d webhook log(s) older than %s would be pruned.',
                $result->count,
                $result->cutoff->toDateTimeString()
            ));

            return;
        }

        $this->info(sprintf(
            'Pruned %d webhook log(s) older than %s.',
            $result->count,
            $result->cutoff->toDateTimeString()
        ));
    }
}
