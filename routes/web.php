<?php

use Illuminate\Support\Facades\Route;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Http\Controllers\DashboardController;

Route::prefix(trim(config('larawebhook.dashboard.path'), '/'))
    ->middleware(config('larawebhook.dashboard.middleware'))
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('larawebhook.dashboard');
    });
