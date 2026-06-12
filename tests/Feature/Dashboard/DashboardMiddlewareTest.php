<?php

use Illuminate\Support\Facades\Route;
use Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Providers\LarawebhookServiceProvider;

it('applies configured dashboard middleware to dashboard routes', function () {
    config()->set('larawebhook.dashboard.enabled', true);
    config()->set('larawebhook.dashboard.middleware', ['web', 'auth']);

    app()->register(LarawebhookServiceProvider::class, true);
    app('router')->getRoutes()->refreshNameLookups();

    $expectedUri = trim(config('larawebhook.dashboard.path'), '/');

    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === $expectedUri);

    expect($route)->not->toBeNull()
        ->and($route->middleware())->toContain('web')
        ->and($route->middleware())->toContain('auth');
});
