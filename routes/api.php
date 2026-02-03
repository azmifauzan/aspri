<?php

use App\Http\Controllers\Api\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Telegram Webhook Routes
Route::prefix('telegram')->group(function () {
    // Main webhook endpoint (public)
    Route::post('webhook', TelegramWebhookController::class)->name('telegram.webhook');

    // Admin endpoints for webhook management (protected)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('webhook/set', [TelegramWebhookController::class, 'setWebhook'])->name('telegram.webhook.set');
        Route::delete('webhook', [TelegramWebhookController::class, 'removeWebhook'])->name('telegram.webhook.remove');
        Route::get('webhook/info', [TelegramWebhookController::class, 'webhookInfo'])->name('telegram.webhook.info');
    });
});
