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

it('can represent processing lifecycle statuses', function () {
    expect(WebhookStatus::received()->isReceived())->toBeTrue()
        ->and(WebhookStatus::validated()->isValidated())->toBeTrue()
        ->and(WebhookStatus::processing()->isProcessing())->toBeTrue()
        ->and(WebhookStatus::processed()->isProcessed())->toBeTrue()
        ->and(WebhookStatus::replayed()->isReplayed())->toBeTrue();
});

it('knows terminal statuses', function () {
    expect(WebhookStatus::processed()->isTerminal())->toBeTrue()
        ->and(WebhookStatus::failed()->isTerminal())->toBeTrue()
        ->and(WebhookStatus::processing()->isTerminal())->toBeFalse();
});

it('knows replayable statuses', function () {
    expect(WebhookStatus::processed()->canBeReplayed())->toBeTrue()
        ->and(WebhookStatus::failed()->canBeReplayed())->toBeTrue()
        ->and(WebhookStatus::received()->canBeReplayed())->toBeFalse();
});
