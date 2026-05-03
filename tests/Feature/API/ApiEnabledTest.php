<?php

use Proxynth\Larawebhook\LarawebhookServiceProvider;

it('registers api routes when api is enabled', function () {
    config()->set('larawebhook.api.enabled', true);

    app()->register(LarawebhookServiceProvider::class, true);

    app('router')->getRoutes()->refreshNameLookups();

    expect(Route::has('larawebhook.api.logs.index'))->toBeTrue()
        ->and(Route::has('larawebhook.api.logs.replay'))->toBeTrue();
});
