<?php

use Illuminate\Support\Facades\Route;

it('does not register api routes when api is disabled', function () {
    app('router')->getRoutes()->refreshNameLookups();

    expect(Route::has('larawebhook.api.logs.index'))->toBeFalse()
        ->and(Route::has('larawebhook.api.logs.replay'))->toBeFalse();
});
