<?php

use App\Http\Controllers\Api\Concierge\FinancialReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/financial-reports')
    ->name('api.financial-reports.')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', [FinancialReportController::class, 'index'])->name('index');
    });
