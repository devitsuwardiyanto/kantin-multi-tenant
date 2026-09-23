<?php

use App\Modules\Payments\Http\Controllers\QrisWebhookController;
use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Webhook pembayaran milik modul Payments (publik, tanpa auth). Keaslian dijamin signature HMAC
 * atas raw body; pengecualian CSRF `webhooks/*` dan limiter `qris-webhook` didaftarkan
 * PaymentsServiceProvider::boot().
 */
PortalRoutes::web(function (): void {
    Route::post('/webhooks/qris', QrisWebhookController::class)
        ->middleware('throttle:qris-webhook')
        ->name('webhooks.qris');
});
