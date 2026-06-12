<?php

use Proxynth\Larawebhook\Processing\Application\Ports\IdempotencyResolver;
use Proxynth\Larawebhook\Processing\Infrastructure\Idempotency\DefaultIdempotencyResolver;

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

it('uses external id before payload hash', function () {
    $resolver = new DefaultIdempotencyResolver;

    $key = $resolver->resolve(
        service: 'stripe',
        payload: ['id' => 'different'],
        externalId: 'evt_123',
        event: 'invoice.paid'
    );

    expect($key)->toBe('evt_123');
});

it('falls back to payload hash when external id is missing', function () {
    $resolver = new DefaultIdempotencyResolver;

    $key = $resolver->resolve(
        service: 'custom',
        payload: [
            'foo' => 'bar',
        ],
        externalId: null,
        event: 'custom.event'
    );

    expect($key)
        ->toBeString()
        ->toStartWith('payload_hash:');
});

it('generates deterministic payload hash for the same payload', function () {
    $resolver = new DefaultIdempotencyResolver;

    $payload = [
        'foo' => 'bar',
        'nested' => [
            'a' => 1,
            'b' => 2,
        ],
    ];

    $first = $resolver->resolve('custom', $payload, null, 'event');
    $second = $resolver->resolve('custom', $payload, null, 'event');

    expect($first)->toBe($second);
});

it('generates the same payload hash regardless of object key order', function () {
    $resolver = new DefaultIdempotencyResolver;

    $first = $resolver->resolve(
        service: 'custom',
        payload: [
            'foo' => 'bar',
            'nested' => [
                'a' => 1,
                'b' => 2,
            ],
        ],
        externalId: null,
        event: 'event'
    );

    $second = $resolver->resolve(
        service: 'custom',
        payload: [
            'nested' => [
                'b' => 2,
                'a' => 1,
            ],
            'foo' => 'bar',
        ],
        externalId: null,
        event: 'event'
    );

    expect($first)->toBe($second);
});

it('keeps list order significant when generating payload hash', function () {
    $resolver = new DefaultIdempotencyResolver;

    $first = $resolver->resolve(
        service: 'custom',
        payload: [
            'items' => [
                ['id' => 1],
                ['id' => 2],
            ],
        ],
        externalId: null,
        event: 'event'
    );

    $second = $resolver->resolve(
        service: 'custom',
        payload: [
            'items' => [
                ['id' => 2],
                ['id' => 1],
            ],
        ],
        externalId: null,
        event: 'event'
    );

    expect($first)->not->toBe($second);
});
