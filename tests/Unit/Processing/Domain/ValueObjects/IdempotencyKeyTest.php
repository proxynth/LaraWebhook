<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Processing\Domain\ValueObjects\IdempotencyKey;

it('can be created from a non-empty string', function () {
    $key = IdempotencyKey::fromString('evt_123');

    expect($key->value())->toBe('evt_123')
        ->and((string) $key)->toBe('evt_123');
});

it('trims the value', function () {
    $key = IdempotencyKey::fromString('  evt_123  ');

    expect($key->value())->toBe('evt_123');
});

it('returns null from nullable when value is null or empty', function () {
    expect(IdempotencyKey::nullable(null))->toBeNull()
        ->and(IdempotencyKey::nullable(''))->toBeNull()
        ->and(IdempotencyKey::nullable('    '))->toBeNull();
});

it('throws when created from an empty string', function () {
    IdempotencyKey::fromString('');
})->throws(InvalidArgumentException::class);

it('can compare two keys', function () {
    expect(IdempotencyKey::fromString('evt_123'))->equals(
        IdempotencyKey::fromString('evt_123')
    )->toBeTrue();
});
