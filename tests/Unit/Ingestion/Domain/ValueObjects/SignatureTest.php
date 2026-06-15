<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;

it('can be created from a non-empty value', function () {
    $signature = Signature::fromString('sha256=abc');

    expect($signature->value())->toBe('sha256=abc')
        ->and($signature->timestamp())->toBeNull()
        ->and((string) $signature)->toBe('sha256=abc');
});

it('can contain a timestamp', function () {
    $signature = Signature::fromString('v0=abc', '1234567890');

    expect($signature->value())->toBe('v0=abc')
        ->and($signature->timestamp())->toBe('1234567890');
});

it('cannot be empty', function () {
    Signature::fromString('');
})->throws(InvalidArgumentException::class);
