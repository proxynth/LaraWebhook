<?php

use Illuminate\Support\Facades\Route;
use Proxynth\Larawebhook\Http\Controllers\DashboardController;

Route::prefix('larawebhook')
    ->middleware('web')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('larawebhook.dashboard');
    });
