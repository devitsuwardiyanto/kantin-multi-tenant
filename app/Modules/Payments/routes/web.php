<?php

use App\Modules\Payments\Http\Controllers\QrisWebhookController;
use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Webhook pembayaran milik modul Payments (publik, tanpa auth). Keaslian dijamin signature HMAC
 * atas raw body; pengecualian CSRF `webhooks/*` didaftarkan PaymentsServiceProvider::boot().
 */
PortalRoutes::web(function (): void {
    Route::post('/webhooks/qris', QrisWebhookController::class)->name('webhooks.qris');
});
