<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Processing\Infrastructure\Config\ConfigRetryPolicyResolver;

it('resolves retry policy from config', function () {
    config()->set('larawebhook.retries.max_attempts', 5);
    config()->set('larawebhook.retries.delays', [3, 9, 27]);

    $policy = (new ConfigRetryPolicyResolver)->resolve();

    expect($policy->maxAttempts)->toBe(5)
        ->and($policy->delays)->toBe([3, 9, 27]);
});

it('falls back to default delays when config is invalid', function () {
    config()->set('larawebhook.retries.max_attempts', 3);
    config()->set('larawebhook.retries.delays', 'invalid');

    $policy = (new ConfigRetryPolicyResolver)->resolve();

    expect($policy->maxAttempts)->toBe(3)
        ->and($policy->delays)->toBe([1, 5, 10]);
});

it('normalizes max attempts to at least one', function () {
    config()->set('larawebhook.retries.max_attempts', 0);
    config()->set('larawebhook.retries.delays', [1]);

    $policy = (new ConfigRetryPolicyResolver)->resolve();

    expect($policy->maxAttempts)->toBe(1);
});
