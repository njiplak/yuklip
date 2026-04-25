<?php

use App\Http\Controllers\Api\Concierge\ActivityController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/activity')
    ->name('api.activity.')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', [ActivityController::class, 'index'])->name('index');
    });
