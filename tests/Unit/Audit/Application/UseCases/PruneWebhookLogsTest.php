<?php

use Proxynth\Larawebhook\Audit\Application\Commands\PruneWebhookLogsCommand;
use Proxynth\Larawebhook\Audit\Application\UseCases\PruneWebhookLogs;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

it('does not prune logs when retention is disabled', function () {
    WebhookLog::factory()->create([
        'created_at' => now()->subDays(60),
    ]);

    $result = app(PruneWebhookLogs::class)->handle(new PruneWebhookLogsCommand(
        retentionEnabled: false,
        cutoff: now()->subDays(30),
        dryRun: false,
    ));

    expect($result->isDisabled())->toBeTrue()
        ->and(WebhookLog::query()->count())->toBe(1);
});

it('counts prunable logs during dry run without deleting them', function () {
    WebhookLog::factory()->create([
        'created_at' => now()->subDays(60),
    ]);

    WebhookLog::factory()->create([
        'created_at' => now()->subDays(10),
    ]);

    $result = app(PruneWebhookLogs::class)->handle(new PruneWebhookLogsCommand(
        retentionEnabled: true,
        cutoff: now()->subDays(30),
        dryRun: true,
    ));

    expect($result->isDryRun())->toBeTrue()
        ->and($result->count)->toBe(1)
        ->and(WebhookLog::query()->count())->toBe(2);
});

it('deletes logs older than the cutoff', function () {
    WebhookLog::factory()->create([
        'created_at' => now()->subDays(60),
    ]);

    WebhookLog::factory()->create([
        'created_at' => now()->subDays(10),
    ]);

    $result = app(PruneWebhookLogs::class)->handle(new PruneWebhookLogsCommand(
        retentionEnabled: true,
        cutoff: now()->subDays(30),
        dryRun: false,
    ));

    expect($result->isDeleted())->toBeTrue()
        ->and($result->count)->toBe(1)
        ->and(WebhookLog::query()->count())->toBe(1);
});
