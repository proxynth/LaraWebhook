<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Proxynth\Larawebhook\Models\WebhookLog;

function createWebhookLogForPrune(array $attributes = []): WebhookLog
{
    return WebhookLog::create(array_merge([
        'service' => 'stripe',
        'external_id' => fake()->uuid(),
        'event' => 'invoice.paid',
        'status' => 'success',
        'payload' => ['id' => fake()->uuid()],
        'attempt' => 1,
        'error_message' => null,
    ], $attributes));
}

it('shows how many logs would be pruned without deleting them', function () {
    Carbon::setTestNow();

    $oldLog = createWebhookLogForPrune();
    $oldLog->forceFill([
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ])->save();

    $recentLog = createWebhookLogForPrune();
    $recentLog->forceFill([
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ])->save();

    expect(WebhookLog::query()->where('created_at', '<', now()->subDays(30))->count())
        ->toBe(1);

    $this->artisan('larawebhook:prune --older-than=30d --dry-run')
        ->expectsOutputToContain('Dry run: 1 webhook log(s)')
        ->assertSuccessful();

    expect(WebhookLog::count())->toBe(2);

    Carbon::setTestNow();
});

it('prunes logs older than the given duration', function () {
    CarbonImmutable::setTestNow();

    $oldLog = createWebhookLogForPrune();
    $oldLog->forceFill([
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ])->save();

    $recentLog = createWebhookLogForPrune();
    $recentLog->forceFill([
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ])->save();

    expect(WebhookLog::query()->where('created_at', '<', now()->subDays(30))->count())
        ->toBe(1);

    $this->artisan('larawebhook:prune --older-than=30d')
        ->expectsOutputToContain('Pruned 1 webhook log(s)')
        ->assertSuccessful();

    expect(WebhookLog::query()->whereKey($oldLog->id)->exists())->toBeFalse()
        ->and(WebhookLog::query()->whereKey($recentLog->id)->exists())->toBeTrue();

    CarbonImmutable::setTestNow();
});

it('uses configured retention days when older-than option is not provided', function () {
    CarbonImmutable::setTestNow('2026-05-29 12:00:00');

    config()->set('larawebhook.retention.enabled', true);
    config()->set('larawebhook.retention.days', 15);

    $oldLog = createWebhookLogForPrune();
    $oldLog->forceFill([
        'created_at' => now()->subDays(20),
        'updated_at' => now()->subDays(20),
    ])->save();

    $recentLog = createWebhookLogForPrune();
    $recentLog->forceFill([
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ])->save();

    $this->artisan('larawebhook:prune')
        ->expectsOutputToContain('Pruned 1 webhook log(s)')
        ->assertSuccessful();

    expect(WebhookLog::query()->whereKey($oldLog->id)->exists())->toBeFalse()
        ->and(WebhookLog::query()->whereKey($recentLog->id)->exists())->toBeTrue();

    CarbonImmutable::setTestNow();
});

it('does not prune logs when retention is disabled', function () {
    config()->set('larawebhook.retention.enabled', false);

    $oldLog = createWebhookLogForPrune();
    $oldLog->forceFill([
        'created_at' => now()->subDays(90),
        'updated_at' => now()->subDays(90),
    ])->save();

    $this->artisan('larawebhook:prune --older-than=30d')
        ->expectsOutput('LaraWebhook retention is disabled. No logs were pruned.')
        ->assertSuccessful();

    expect(WebhookLog::count())->toBe(1);
});

it('fails when older-than option is invalid', function () {
    $this->artisan('larawebhook:prune --older-than=soon')
        ->expectsOutput('Invalid --older-than value. Use a duration like 7d, 30d, 12h, or 60m.')
        ->assertFailed();
});
