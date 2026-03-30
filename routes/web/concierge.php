<?php

use App\Http\Controllers\Concierge\BookingController;
use App\Http\Controllers\Concierge\OfferController;
use App\Http\Controllers\Concierge\SystemLogController;
use App\Http\Controllers\Concierge\TransactionController;
use App\Http\Controllers\Concierge\UpsellLogController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth', 'prefix' => 'concierge', 'as' => 'backoffice.concierge.'], function () {

    Route::group(['prefix' => 'booking', 'as' => 'booking.'], function () {
        Route::get('/', [BookingController::class, 'index'])->name('index');
        Route::get('/fetch', [BookingController::class, 'fetch'])->name('fetch');
        Route::get('/create', [BookingController::class, 'create'])->name('create');
        Route::post('/', [BookingController::class, 'store'])->name('store');
        Route::get('/{id}', [BookingController::class, 'show'])->name('show');
        Route::put('/{id}', [BookingController::class, 'update'])->name('update');
        Route::delete('/{id}', [BookingController::class, 'destroy'])->name('destroy');
        Route::post('/destroy-bulk', [BookingController::class, 'destroy_bulk'])->name('destroy-bulk');
    });

    Route::group(['prefix' => 'offer', 'as' => 'offer.'], function () {
        Route::get('/', [OfferController::class, 'index'])->name('index');
        Route::get('/fetch', [OfferController::class, 'fetch'])->name('fetch');
        Route::get('/create', [OfferController::class, 'create'])->name('create');
        Route::post('/', [OfferController::class, 'store'])->name('store');
        Route::get('/{id}', [OfferController::class, 'show'])->name('show');
        Route::put('/{id}', [OfferController::class, 'update'])->name('update');
        Route::delete('/{id}', [OfferController::class, 'destroy'])->name('destroy');
        Route::post('/destroy-bulk', [OfferController::class, 'destroy_bulk'])->name('destroy-bulk');
    });

    Route::group(['prefix' => 'upsell-log', 'as' => 'upsell-log.'], function () {
        Route::get('/', [UpsellLogController::class, 'index'])->name('index');
        Route::get('/fetch', [UpsellLogController::class, 'fetch'])->name('fetch');
    });

    Route::group(['prefix' => 'transaction', 'as' => 'transaction.'], function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::get('/fetch', [TransactionController::class, 'fetch'])->name('fetch');
        Route::get('/create', [TransactionController::class, 'create'])->name('create');
        Route::post('/', [TransactionController::class, 'store'])->name('store');
        Route::get('/{id}', [TransactionController::class, 'show'])->name('show');
        Route::put('/{id}', [TransactionController::class, 'update'])->name('update');
        Route::delete('/{id}', [TransactionController::class, 'destroy'])->name('destroy');
        Route::post('/destroy-bulk', [TransactionController::class, 'destroy_bulk'])->name('destroy-bulk');
    });

    Route::group(['prefix' => 'system-log', 'as' => 'system-log.'], function () {
        Route::get('/', [SystemLogController::class, 'index'])->name('index');
        Route::get('/fetch', [SystemLogController::class, 'fetch'])->name('fetch');
    });
});
