<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Proxynth\Larawebhook\Audit\Application\Queries\GetWebhookLogDetails;
use Proxynth\Larawebhook\Audit\Application\Queries\GetWebhookLogDetailsQuery;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogReadModel;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

it('gets webhook log details', function () {
    $log = WebhookLog::factory()->create([
        'service' => 'stripe',
        'event' => 'invoice.paid',
    ]);

    $result = app(GetWebhookLogDetails::class)->handle(
        new GetWebhookLogDetailsQuery($log->id)
    );

    expect($result)->toBeInstanceOf(WebhookLogReadModel::class)
        ->and($result->id)->toBe($log->id)
        ->and($result->service)->toBe('stripe')
        ->and($result->event)->toBe('invoice.paid');
});

it('throws when webhook log does not exist', function () {
    app(GetWebhookLogDetails::class)->handle(
        new GetWebhookLogDetailsQuery(999999)
    );
})->throws(ModelNotFoundException::class);
