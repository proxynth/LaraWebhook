<?php

use Illuminate\Support\Facades\Route;
use Proxynth\Larawebhook\Http\Controllers\DashboardController;

Route::prefix(config('larawebhook.dashboard.path'))
    ->middleware('web')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('larawebhook.dashboard');
    });
