<?php

use App\Http\Controllers\Api\Concierge\BookingController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/bookings')
    ->name('api.bookings.')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', [BookingController::class, 'index'])->name('index');
        Route::get('/{id}', [BookingController::class, 'show'])->name('show')->whereNumber('id');
    });
