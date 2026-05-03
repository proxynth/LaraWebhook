<?php

use Proxynth\Larawebhook\LarawebhookServiceProvider;

it('applies configured api middleware to api routes', function () {
    config()->set('larawebhook.api.enabled', true);
    config()->set('larawebhook.api.middleware', ['api', 'auth:sanctum']);

    app()->register(LarawebhookServiceProvider::class, true);

    app('router')->getRoutes()->refreshNameLookups();

    $indexRoute = Route::getRoutes()->getByName('larawebhook.api.logs.index');
    $replayRoute = Route::getRoutes()->getByName('larawebhook.api.logs.replay');

    expect($indexRoute)->not->toBeNull()
        ->and($replayRoute)->not->toBeNull()
        ->and($indexRoute->middleware())->toContain('api')
        ->and($indexRoute->middleware())->toContain('auth:sanctum')
        ->and($replayRoute->middleware())->toContain('api')
        ->and($replayRoute->middleware())->toContain('auth:sanctum');
});
