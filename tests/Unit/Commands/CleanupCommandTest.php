<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Proxynth\Larawebhook\Commands\CleanupCommand;
use Proxynth\Larawebhook\Models\WebhookLog;

describe('CleanupCommand', function () {
    it('is an Artisan command', function () {
        $command = new CleanupCommand;

        expect($command)->toBeInstanceOf(\Illuminate\Console\Command::class);
    });

    it('has correct signature', function () {
        $command = new CleanupCommand;

        expect($command->getName())->toBe('larawebhook:cleanup');
    });

    it('has correct description', function () {
        $command = new CleanupCommand;

        expect($command->getDescription())->toBe('Clean up old webhook logs from the database');
    });

    it('is registered in Artisan', function () {
        $commands = Artisan::all();

        expect($commands)->toHaveKey('larawebhook:cleanup');
    });
});

describe('CleanupCommand execution', function () {
    beforeEach(function () {
        WebhookLog::query()->delete();
    });

    /**
     * Helper to create a webhook log with a specific created_at date.
     */
    function createOldLog(string $service, string $status, int $daysAgo): WebhookLog
    {
        $log = WebhookLog::create([
            'service' => $service,
            'event' => 'test',
            'status' => $status,
            'payload' => [],
        ]);

        // Update created_at directly in database to bypass Eloquent timestamps
        WebhookLog::where('id', $log->id)->update([
            'created_at' => now()->subDays($daysAgo),
        ]);

        return $log->fresh();
    }

    it('reports no logs when database is empty', function () {
        $this->artisan('larawebhook:cleanup')
            ->expectsOutput('No webhook logs found matching the criteria.')
            ->assertSuccessful();
    });

    it('reports no logs when all logs are recent', function () {
        createOldLog('stripe', 'success', 5);

        $this->artisan('larawebhook:cleanup --days=30')
            ->expectsOutput('No webhook logs found matching the criteria.')
            ->assertSuccessful();
    });

    it('deletes old logs with confirmation', function () {
        createOldLog('stripe', 'success', 40);

        $this->artisan('larawebhook:cleanup --days=30')
            ->expectsConfirmation('Delete 1 webhook log(s) older than 30 days?', 'yes')
            ->expectsOutput('Successfully deleted 1 webhook log(s).')
            ->assertSuccessful();

        expect(WebhookLog::count())->toBe(0);
    });

    it('respects custom days option', function () {
        createOldLog('stripe', 'success', 10);

        $this->artisan('larawebhook:cleanup --days=7')
            ->expectsConfirmation('Delete 1 webhook log(s) older than 7 days?', 'yes')
            ->assertSuccessful();

        expect(WebhookLog::count())->toBe(0);
    });

    it('filters by status', function () {
        createOldLog('stripe', 'success', 40);
        createOldLog('stripe', 'failed', 40);

        $this->artisan('larawebhook:cleanup --days=30 --status=failed')
            ->expectsConfirmation('Delete 1 webhook log(s) older than 30 days?', 'yes')
            ->assertSuccessful();

        expect(WebhookLog::count())->toBe(1);
        expect(WebhookLog::first()->status)->toBe('success');
    });

    it('filters by service', function () {
        createOldLog('stripe', 'success', 40);
        createOldLog('github', 'success', 40);

        $this->artisan('larawebhook:cleanup --days=30 --service=stripe')
            ->expectsConfirmation('Delete 1 webhook log(s) older than 30 days?', 'yes')
            ->assertSuccessful();

        expect(WebhookLog::count())->toBe(1);
        expect(WebhookLog::first()->service)->toBe('github');
    });

    it('cancels on negative confirmation', function () {
        createOldLog('stripe', 'success', 40);

        $this->artisan('larawebhook:cleanup --days=30')
            ->expectsConfirmation('Delete 1 webhook log(s) older than 30 days?', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertSuccessful();

        expect(WebhookLog::count())->toBe(1);
    });

    it('shows preview in dry-run mode without deleting', function () {
        createOldLog('stripe', 'success', 40);

        $this->artisan('larawebhook:cleanup --days=30 --dry-run')
            ->expectsOutput('Would delete 1 webhook log(s) older than 30 days.')
            ->assertSuccessful();

        expect(WebhookLog::count())->toBe(1);
    });

    it('deletes multiple logs', function () {
        for ($i = 0; $i < 5; $i++) {
            createOldLog('stripe', $i % 2 === 0 ? 'success' : 'failed', 40 + $i);
        }

        $this->artisan('larawebhook:cleanup --days=30')
            ->expectsConfirmation('Delete 5 webhook log(s) older than 30 days?', 'yes')
            ->expectsOutput('Successfully deleted 5 webhook log(s).')
            ->assertSuccessful();

        expect(WebhookLog::count())->toBe(0);
    });
});
