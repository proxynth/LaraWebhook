<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Processing\Domain\ValueObjects\DeliveryAttempt;

it('starts at zero', function () {
    expect(DeliveryAttempt::initial()->value())->toBe(0);
});

it('can be created from a positive integer', function () {
    expect(DeliveryAttempt::fromInt(2)->value())->toBe(2);
});

it('can create the next attempt', function () {
    expect(DeliveryAttempt::fromInt(2)->next()->value())->toBe(3);
});

it('throws when attempt is negative', function () {
    DeliveryAttempt::fromInt(-1);
})->throws(InvalidArgumentException::class);
