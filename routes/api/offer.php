<?php

use App\Http\Controllers\Api\Concierge\OfferController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/offers')
    ->name('api.offers.')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', [OfferController::class, 'index'])->name('index');
        Route::post('/', [OfferController::class, 'store'])->name('store');
        Route::get('/{id}', [OfferController::class, 'show'])->name('show')->whereNumber('id');
        Route::put('/{id}', [OfferController::class, 'update'])->name('update')->whereNumber('id');
    });
