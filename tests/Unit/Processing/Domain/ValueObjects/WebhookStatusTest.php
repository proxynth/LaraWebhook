<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Processing\Domain\ValueObjects\WebhookStatus;

it('can represent success status', function () {
    $status = WebhookStatus::success();

    expect($status->isSuccess())->toBeTrue()
        ->and($status->isFailed())->toBeFalse()
        ->and($status->value())->toBe('success');
});

it('can represent failed status', function () {
    $status = WebhookStatus::failed();

    expect($status->isFailed())->toBeTrue()
        ->and($status->isSuccess())->toBeFalse()
        ->and($status->value())->toBe('failed');
});

it('can be created from a valid string', function () {
    expect(WebhookStatus::fromString('success')->isSuccess())->toBeTrue()
        ->and(WebhookStatus::fromString('failed')->isFailed())->toBeTrue();
});

it('throws when created from an invalid string', function () {
    WebhookStatus::fromString('pending');
})->throws(InvalidArgumentException::class);
