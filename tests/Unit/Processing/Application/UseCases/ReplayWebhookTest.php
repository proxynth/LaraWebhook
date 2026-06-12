<?php

use Proxynth\Larawebhook\Audit\Domain\Exceptions\PayloadNotAvailable;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Processing\Application\Commands\ReplayWebhookCommand;
use Proxynth\Larawebhook\Processing\Application\UseCases\ReplayWebhook;

it('throws when replaying a log without payload', function () {
    $log = new WebhookLog([
        'service' => 'stripe',
        'event' => 'invoice.paid',
        'payload' => null,
        'attempt' => 1,
    ]);

    $log->id = 123;

    $useCase = app(ReplayWebhook::class);

    $useCase->handle(new ReplayWebhookCommand(
        log: $log,
        signature: 'signature'
    ));
})->throws(PayloadNotAvailable::class);

it('throws when replaying a log with an empty payload', function () {
    $log = new WebhookLog([
        'service' => 'stripe',
        'event' => 'invoice.paid',
        'payload' => [],
        'attempt' => 1,
    ]);

    $log->id = 123;

    $useCase = app(ReplayWebhook::class);

    $useCase->handle(new ReplayWebhookCommand(
        log: $log,
        signature: 'signature',
    ));
})->throws(PayloadNotAvailable::class);
