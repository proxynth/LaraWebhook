<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Audit\Domain\ValueObjects\PayloadStorageMode;

it('accepts valid payload storage modes', function () {
    expect(PayloadStorageMode::fromConfig('none'))->toBe(PayloadStorageMode::None)
        ->and(PayloadStorageMode::fromConfig('redacted'))->toBe(PayloadStorageMode::Redacted)
        ->and(PayloadStorageMode::fromConfig('full'))->toBe(PayloadStorageMode::Full);
});

it('rejects invalid payload storage mode', function () {
    PayloadStorageMode::fromConfig('invalid');
})->throws(InvalidArgumentException::class);

it('knows whether it stores payload', function () {
    expect(PayloadStorageMode::None->storesPayload())->toBeFalse()
        ->and(PayloadStorageMode::Redacted->storesPayload())->toBeTrue()
        ->and(PayloadStorageMode::Full->storesPayload())->toBeTrue();
});
