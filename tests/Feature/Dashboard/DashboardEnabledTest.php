<?php

use Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Providers\LarawebhookServiceProvider;

it('does register dashboard route when enabled', function () {
    config()->set('larawebhook.dashboard.enabled', true);

    app()->register(LarawebhookServiceProvider::class, true);

    app('router')->getRoutes()->refreshNameLookups();

    expect(Route::has('larawebhook.dashboard'))->toBeTrue();
});
