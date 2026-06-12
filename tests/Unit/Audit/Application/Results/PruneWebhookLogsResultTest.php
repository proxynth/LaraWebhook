<?php

use Illuminate\Support\Carbon;
use Proxynth\Larawebhook\Audit\Application\Results\PruneWebhookLogsResult;

it('can represent disabled pruning', function () {
    $result = PruneWebhookLogsResult::disabled();

    expect($result->isDisabled())->toBeTrue()
        ->and($result->count)->toBe(0)
        ->and($result->cutoff)->toBeNull();
});

it('can represent dry run pruning', function () {
    $cutoff = Carbon::parse('2026-05-01 12:00:00');

    $result = PruneWebhookLogsResult::dryRun(12, $cutoff);

    expect($result->isDryRun())->toBeTrue()
        ->and($result->count)->toBe(12)
        ->and($result->cutoff)->toBe($cutoff);
});

it('can represent deleted pruning', function () {
    $cutoff = Carbon::parse('2026-05-01 12:00:00');

    $result = PruneWebhookLogsResult::deleted(8, $cutoff);

    expect($result->isDeleted())->toBeTrue()
        ->and($result->count)->toBe(8)
        ->and($result->cutoff)->toBe($cutoff);
});
