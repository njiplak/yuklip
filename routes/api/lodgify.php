<?php

use App\Http\Controllers\Lodgify\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/lodgify/webhook', [WebhookController::class, 'handle'])->name('lodgify.webhook');
