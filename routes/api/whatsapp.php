<?php

use App\Http\Controllers\WhatsApp\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/whatsapp/webhook', [WebhookController::class, 'handle'])->name('whatsapp.webhook');
