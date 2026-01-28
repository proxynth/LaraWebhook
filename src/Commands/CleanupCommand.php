<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Commands;

use Illuminate\Console\Command;
use Proxynth\Larawebhook\Models\WebhookLog;

/**
 * Command to clean up old webhook logs.
 *
 * Usage:
 *   php artisan larawebhook:cleanup         # Delete logs older than 30 days
 *   php artisan larawebhook:cleanup --days=7  # Delete logs older than 7 days
 *   php artisan larawebhook:cleanup --status=failed  # Delete only failed logs
 *   php artisan larawebhook:cleanup --dry-run  # Show what would be deleted
 */
class CleanupCommand extends Command
{
    public $signature = 'larawebhook:cleanup
                        {--days=30 : Delete logs older than this many days}
                        {--status= : Filter by status (success, failed)}
                        {--service= : Filter by service (stripe, github, etc.)}
                        {--dry-run : Show what would be deleted without deleting}';

    public $description = 'Clean up old webhook logs from the database';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $status = $this->option('status');
        $service = $this->option('service');
        $dryRun = $this->option('dry-run');

        $cutoffDate = now()->subDays($days);

        $query = WebhookLog::where('created_at', '<', $cutoffDate);

        if ($status) {
            $query->where('status', $status);
        }

        if ($service) {
            $query->where('service', $service);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No webhook logs found matching the criteria.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Would delete {$count} webhook log(s) older than {$days} days.");
            $this->showBreakdown($query->clone());

            return self::SUCCESS;
        }

        if (! $this->confirm("Delete {$count} webhook log(s) older than {$days} days?", true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Successfully deleted {$deleted} webhook log(s).");

        return self::SUCCESS;
    }

    /**
     * Show breakdown of logs to be deleted.
     */
    private function showBreakdown($query): void
    {
        $breakdown = $query
            ->selectRaw('service, status, COUNT(*) as count')
            ->groupBy('service', 'status')
            ->get();

        $this->newLine();
        $this->table(
            ['Service', 'Status', 'Count'],
            $breakdown->map(fn ($row) => [$row->service, $row->status, $row->count])
        );
    }
}
