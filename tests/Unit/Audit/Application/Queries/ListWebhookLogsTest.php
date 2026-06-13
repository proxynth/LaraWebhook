<?php

use Proxynth\Larawebhook\Audit\Application\Queries\ListWebhookLogs;
use Proxynth\Larawebhook\Audit\Application\Queries\ListWebhookLogsQuery;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogReadModel;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

it('lists webhook logs ordered by latest first', function () {
    $older = WebhookLog::factory()->create([
        'service' => 'stripe',
        'created_at' => now()->subDays(2),
    ]);

    $newer = WebhookLog::factory()->create([
        'service' => 'github',
        'created_at' => now(),
    ]);

    $result = app(ListWebhookLogs::class)->handle(new ListWebhookLogsQuery(
        perPage: 25,
    ));

    expect($result->items())->each->toBeInstanceOf(WebhookLogReadModel::class)
        ->and($result->items()[0]->id)->toBe($newer->id)
        ->and($result->items()[1]->id)->toBe($older->id);
});

it('filters webhook logs by service', function () {
    WebhookLog::factory()->create([
        'service' => 'stripe',
    ]);

    WebhookLog::factory()->create([
        'service' => 'github',
    ]);

    $result = app(ListWebhookLogs::class)->handle(new ListWebhookLogsQuery(
        service: 'stripe',
        perPage: 25,
    ));

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->service)->toBe('stripe');
});

it('filters webhook logs by status', function () {
    WebhookLog::factory()->create([
        'status' => 'success',
    ]);

    WebhookLog::factory()->create([
        'status' => 'failed',
    ]);

    $result = app(ListWebhookLogs::class)->handle(new ListWebhookLogsQuery(
        status: 'failed',
        perPage: 25,
    ));

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->status)->toBe('failed');
});
