<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Processing\Domain\ValueObjects\EventType;

it('can be created from a non-empty string', function () {
    $eventType = EventType::fromString('invoice.paid');

    expect($eventType->value())->toBe('invoice.paid')
        ->and((string) $eventType)->toBe('invoice.paid');
});

it('trims values', function () {
    expect(EventType::fromString('  push  ')->value())->toBe('push');
});

it('cannot be empty', function () {
    EventType::fromString('');
})->throws(InvalidArgumentException::class);

it('can represent unknown event type', function () {
    $eventType = EventType::unknown();

    expect($eventType->value())->toBe('unknown')
        ->and($eventType->isUnknown())->toBeTrue();
});
