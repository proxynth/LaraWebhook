<?php

use Illuminate\Http\Request;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogSummary;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Http\Resources\WebhookLogResource;

it('serializes webhook log summary without payload', function () {
    $resource = new WebhookLogResource(new WebhookLogSummary(
        id: 1,
        service: 'github',
        event: 'push',
        status: 'success',
        attempt: 0,
        externalId: 'delivery_123',
        createdAt: '2026-06-16T12:00:00+00:00',
    ));

    $data = $resource->toArray(Request::create('/'));

    expect($data)->toHaveKeys([
        'id',
        'service',
        'event',
        'status',
        'attempt',
        'external_id',
        'created_at',
    ])->not->toHaveKey('payload');
});

it('serializes webhook log details with payload', function () {
    $resource = new WebhookLogResource(new WebhookLogDetails(
        id: 1,
        service: 'github',
        event: 'push',
        status: 'failed',
        payload: ['ref' => 'main'],
        errorMessage: 'Invalid signature.',
        attempt: 1,
        externalId: 'delivery_123',
        createdAt: '2026-06-16T12:00:00+00:00',
        updatedAt: '2026-06-16T12:01:00+00:00',
    ));

    $data = $resource->toArray(Request::create('/'));

    expect($data)->toHaveKey('payload')
        ->and($data['payload'])->toBe(['ref' => 'main'])
        ->and($data['error_message'])->toBe('Invalid signature.');
});
