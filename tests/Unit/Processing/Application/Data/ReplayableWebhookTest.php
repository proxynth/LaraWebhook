<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Processing\Application\Data\ReplayableWebhook;

it('restores webhook event from replayable webhook state', function () {
    $replayable = new ReplayableWebhook(
        id: 1,
        service: 'github',
        event: 'opened',
        payload: ['action' => 'opened'],
        attempt: 0,
        externalId: 'delivery_123',
        idempotencyKey: 'delivery_123',
        status: 'failed',
    );

    $event = $replayable->toWebhookEvent();

    expect($event->provider()->value())->toBe('github')
        ->and($event->eventType()->value())->toBe('opened')
        ->and($event->status()->value())->toBe('failed')
        ->and($event->idempotencyKey()?->value())->toBe('delivery_123');
});
