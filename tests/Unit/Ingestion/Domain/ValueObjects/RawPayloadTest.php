<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;

it('can be created from a non-empty string', function () {
    $payload = RawPayload::fromString('{"event":"test"}');

    expect($payload->value())->toBe('{"event":"test"}')
        ->and((string) $payload)->toBe('{"event":"test"}');
});

it('cannot be empty', function () {
    RawPayload::fromString('');
})->throws(InvalidArgumentException::class);

it('can decode json payload', function () {
    expect(RawPayload::fromString('{"event":"test"}')->decoded())
        ->toBe(['event' => 'test']);
});

it('returns an empty array when payload is not valid json object', function () {
    $payload = RawPayload::fromString('invalid');

    expect($payload->isValidJson())->toBeFalse()
        ->and($payload->decoded())->toBe(['raw' => 'invalid']);
});

it('identifies valid json payloads', function () {
    expect(RawPayload::fromString('{"event":"test"}')->isValidJson())->toBeTrue();
});
