<?php

use Illuminate\Support\Facades\Route;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Http\Controllers\WebhookLogController;

Route::prefix(trim(config('larawebhook.api.path', 'api/larawebhook'), '/'))
    ->middleware(config('larawebhook.api.middleware', ['api']))
    ->group(function () {
        Route::get('/logs', [WebhookLogController::class, 'index'])->name('larawebhook.api.logs.index');
        Route::post('/logs/{log}/replay', [WebhookLogController::class, 'replay'])->name('larawebhook.api.logs.replay');
    });
