<?php

use App\Http\Controllers\Api\Concierge\OccupancyController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/occupancy')
    ->name('api.occupancy.')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', [OccupancyController::class, 'index'])->name('index');
    });
