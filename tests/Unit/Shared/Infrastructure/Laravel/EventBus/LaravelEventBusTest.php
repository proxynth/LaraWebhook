<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\Dispatcher;
use Proxynth\Larawebhook\Audit\Domain\Events\WebhookLogged;
use Proxynth\Larawebhook\Ingestion\Domain\Events\WebhookReceived;
use Proxynth\Larawebhook\Shared\Infrastructure\Laravel\EventBus\LaravelEventBus;

it('dispatches a single domain event through the laravel dispatcher', function () {
    $event = new WebhookReceived(
        provider: 'github',
        event: 'push',
        externalId: 'delivery_123',
    );

    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->with($event);

    $bus = new LaravelEventBus($dispatcher);

    $bus->dispatch($event);
});

it('dispatches many domain events in order', function () {
    $events = [
        new WebhookReceived(
            provider: 'github',
            event: 'push',
            externalId: 'delivery_123',
        ),
        new WebhookLogged(
            webhookLogId: 42,
            provider: 'github',
            event: 'push',
            status: 'success',
        ),
    ];

    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->with($events[0]);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->with($events[1]);

    $bus = new LaravelEventBus($dispatcher);

    $bus->dispatchMany($events);
});
