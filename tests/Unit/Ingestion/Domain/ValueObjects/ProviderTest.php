<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Provider;

it('can be created from a non-empty string', function () {
    $provider = Provider::fromString('stripe');

    expect($provider->value())->toBe('stripe')
        ->and((string) $provider)->toBe('stripe');
});

it('trims provider value', function () {
    expect(Provider::fromString('  stripe  ')->value())->toBe('stripe');
});

it('cannot be empty', function () {
    Provider::fromString('');
})->throws(InvalidArgumentException::class);
