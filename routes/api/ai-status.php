<?php

use App\Http\Controllers\Api\Concierge\AiStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/ai-status')
    ->name('api.ai-status.')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', [AiStatusController::class, 'index'])->name('index');
    });
