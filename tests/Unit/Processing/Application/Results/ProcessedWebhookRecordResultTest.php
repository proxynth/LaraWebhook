<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Processing\Application\Results\ProcessedWebhookRecordResult;

it('represents recorded processed webhook event', function () {
    $result = ProcessedWebhookRecordResult::recorded();

    expect($result->recorded)->toBeTrue()
        ->and($result->alreadyRecorded)->toBeFalse();
});

it('represents already recorded processed webhook event', function () {
    $result = ProcessedWebhookRecordResult::alreadyRecorded();

    expect($result->recorded)->toBeFalse()
        ->and($result->alreadyRecorded)->toBeTrue();
});
