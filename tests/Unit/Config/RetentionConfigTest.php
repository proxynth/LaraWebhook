<?php

it('has retention enabled by default', function () {
    expect(config('larawebhook.retention.enabled'))->toBeTrue();
});

it('keeps webhook logs for 30 days by default', function () {
    expect(config('larawebhook.retention.days'))->toBe(30);
});
