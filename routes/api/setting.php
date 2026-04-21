<?php

use App\Http\Controllers\Api\Setting\SettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/settings')
    ->name('api.settings.')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', [SettingController::class, 'index'])->name('index');
        Route::get('/{id}', [SettingController::class, 'show'])->name('show')->whereNumber('id');
        Route::put('/{id}', [SettingController::class, 'update'])->name('update')->whereNumber('id');
    });
