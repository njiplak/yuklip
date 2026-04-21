<?php

use App\Http\Controllers\Api\Auth\UserApiAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/auth')->name('api.auth.')->group(function () {
    Route::post('login', [UserApiAuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [UserApiAuthController::class, 'logout'])->name('logout');
        Route::get('me', [UserApiAuthController::class, 'me'])->name('me');
    });
});
