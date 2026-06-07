<?php

use Proxynth\Larawebhook\Contracts\IdempotencyResolver;
use Proxynth\Larawebhook\Services\DefaultIdempotencyResolver;

it('binds idempotency resolver contract to default implementation', function () {
    expect(app(IdempotencyResolver::class))
        ->toBeInstanceOf(DefaultIdempotencyResolver::class);
});

it('uses the external id as the default idempotency key', function () {
    $resolver = new DefaultIdempotencyResolver;

    $key = $resolver->resolve(
        service: 'stripe',
        payload: ['id' => 'evt_123'],
        externalId: 'evt_123',
        event: 'invoice.paid'
    );

    expect($key)->toBe('evt_123');
});

it('returns null when no external id is available', function () {
    $resolver = new DefaultIdempotencyResolver;

    $key = $resolver->resolve(
        service: 'custom',
        payload: ['id' => 'evt_123'],
        externalId: null,
        event: 'unknown'
    );

    expect($key)->toBeNull();
});
