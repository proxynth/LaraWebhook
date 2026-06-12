<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

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

    public function handle(): int
    {
        if (! config('larawebhook.retention.enabled', true)) {
            $this->warn('LaraWebhook retention is disabled. No logs were pruned.');

            return self::SUCCESS;
        }

        $olderThan = $this->option('older-than');
        $cutoff = $olderThan
            ? $this->cutoffFromDuration($olderThan)
            : now()->subDays((int) config('larawebhook.retention.days', 30));

        if (! $cutoff) {
            $this->error('Invalid --older-than value. Use a duration like 7d, 30d, 12h, or 60m.');

            return self::FAILURE;
        }

        $query = WebhookLog::query()
            ->where('created_at', '<', $cutoff);
        $count = $query->count();

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                'Dry run: %d webhook log(s) older than %s would be pruned.',
                $count,
                $cutoff->toDateTimeString()
            ));

            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info(sprintf(
            'Pruned %d webhook log(s) older than %s.',
            $deleted,
            $cutoff->toDateTimeString()
        ));

        return self::SUCCESS;
    }

    private function cutoffFromDuration(string $olderThan): ?CarbonImmutable
    {
        if (! preg_match('/^(\d+)([dhm])$/', $olderThan, $matches)) {
            return null;
        }

        $amount = (int) $matches[1];

        if ($amount <= 0) {
            return null;
        }

        return match ($matches[2]) {
            'd' => CarbonImmutable::now()->subDays($amount),
            'h' => CarbonImmutable::now()->subHours($amount),
            'm' => CarbonImmutable::now()->subMinutes($amount),
        };
    }
}
