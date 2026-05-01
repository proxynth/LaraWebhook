<?php

it('does not register dashboard routes when dashboard is disabled', function () {
    config()->set('larawebhook.dashboard.enabled', false);

    expect(Route::has('larawebhook.dashboard'))->toBeFalse();

    $this->get('/larawebhook/dashboard')
        ->assertNotFound();
});
