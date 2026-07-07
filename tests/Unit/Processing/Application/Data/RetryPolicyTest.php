<?php

use Proxynth\Larawebhook\Processing\Application\Data\RetryPolicy;

it('uses configured delay for current attempt', function () {
    $retryPolicy = new RetryPolicy(
        maxAttempts: 3,
        delays: [1, 5, 10],
    );

    expect($retryPolicy->delayForAttempt(0))->toBe(1)
        ->and($retryPolicy->delayForAttempt(1))->toBe(5);
});

it('uses last delay when current attempt has no configured delay', function () {
    $retryPolicy = new RetryPolicy(
        maxAttempts: 4,
        delays: [2],
    );

    expect($retryPolicy->delayForAttempt(1))->toBe(2)
        ->and($retryPolicy->delayForAttempt(2))->toBe(2);
});

it('returns null delay when retry is not allowed', function () {
    $retryPolicy = new RetryPolicy(
        maxAttempts: 1,
        delays: [1, 5, 10],
    );

    expect($retryPolicy->shouldRetryAfter(0))->toBeFalse()
        ->and($retryPolicy->nextAttemptAfter(0))->toBeNull()
        ->and($retryPolicy->delayForAttempt(0))->toBeNull();
});
