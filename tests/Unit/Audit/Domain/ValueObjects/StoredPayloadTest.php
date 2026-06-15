<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Audit\Domain\ValueObjects\StoredPayload;

it('can represent no stored payload', function () {
    $payload = StoredPayload::none();

    expect($payload->value())->toBeNull()
        ->and($payload->isStored())->toBeFalse();
});

it('can represent full stored payload', function () {
    $payload = StoredPayload::full(['email' => 'client@example.com']);

    expect($payload->value())->toBe(['email' => 'client@example.com'])
        ->and($payload->isStored())->toBeTrue();
});

it('can represent redacted stored payload', function () {
    $payload = StoredPayload::redacted(['email' => '[REDACTED]']);

    expect($payload->value())->toBe(['email' => '[REDACTED]'])
        ->and($payload->isStored())->toBeTrue();
});
