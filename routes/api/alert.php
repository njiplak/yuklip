<?php

use App\Http\Controllers\Api\Concierge\AlertController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/alerts')
    ->name('api.alerts.')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', [AlertController::class, 'index'])->name('index');
    });
