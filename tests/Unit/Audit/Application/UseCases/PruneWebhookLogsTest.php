<?php

use Carbon\CarbonImmutable;
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

it('returns disabled result when retention is disabled', function () {
    $repository = new FakeWebhookLogRepository(count: 10, deleted: 10);
    $useCase = new PruneWebhookLogs($repository);
    $cutoff = CarbonImmutable::parse('2026-06-01 00:00:00');

    $result = $useCase->handle(new PruneWebhookLogsCommand(
        retentionEnabled: false,
        cutoff: $cutoff,
        dryRun: false,
    ));

    expect($result->status)->toBe('disabled')
        ->and($repository->countOlderThanCalls)->toBe(0)
        ->and($repository->deleteOlderThanCalls)->toBe(0);
});

it('counts old webhook logs without deleting them during dry run', function () {
    $repository = new FakeWebhookLogRepository(count: 12, deleted: 0);
    $useCase = new PruneWebhookLogs($repository);

    $cutoff = CarbonImmutable::parse('2026-06-01 00:00:00');

    $result = $useCase->handle(new PruneWebhookLogsCommand(
        retentionEnabled: true,
        cutoff: $cutoff,
        dryRun: true,
    ));

    expect($result->status)->toBe('dry_run')
        ->and($result->count)->toBe(12)
        ->and($result->cutoff)->toBe($cutoff)
        ->and($repository->countOlderThanCalls)->toBe(1)
        ->and($repository->deleteOlderThanCalls)->toBe(0)
        ->and($repository->lastCountCutoff)->toBe($cutoff);
});

it('deletes old webhook logs when dry run is disabled', function () {
    $repository = new FakeWebhookLogRepository(count: 12, deleted: 9);
    $useCase = new PruneWebhookLogs($repository);

    $cutoff = CarbonImmutable::parse('2026-06-01 00:00:00');

    $result = $useCase->handle(new PruneWebhookLogsCommand(
        retentionEnabled: true,
        cutoff: $cutoff,
        dryRun: false,

    ));

    expect($result->status)->toBe('deleted')
        ->and($result->count)->toBe(9)
        ->and($result->cutoff)->toBe($cutoff)
        ->and($repository->countOlderThanCalls)->toBe(1)
        ->and($repository->deleteOlderThanCalls)->toBe(1)
        ->and($repository->lastCountCutoff)->toBe($cutoff)
        ->and($repository->lastDeleteCutoff)->toBe($cutoff);
});
