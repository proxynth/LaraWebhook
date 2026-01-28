<?php

use Illuminate\Support\Facades\Route;
use Proxynth\Larawebhook\Http\Controllers\API\WebhookLogController;

Route::prefix('api/larawebhook')
    ->middleware('api')
    ->group(function () {
        Route::get('/logs', [WebhookLogController::class, 'index'])->name('larawebhook.api.logs.index');
        Route::post('/logs/{log}/replay', [WebhookLogController::class, 'replay'])->name('larawebhook.api.logs.replay');
    });
