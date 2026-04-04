<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware('auth')->group(function () {
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store']);
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy']);
});
