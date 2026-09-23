<?php

use App\Modules\Ordering\Http\Controllers\ResolveTableQrController;
use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Entry point pelanggan via QR meja milik modul Ordering. Token opaque di URL; rate limited
 * (limiter qr-scan didaftarkan OrderingServiceProvider); canteen ditentukan dari token.
 */
PortalRoutes::web(function (): void {
    Route::get('/q/{token}', ResolveTableQrController::class)
        ->middleware('throttle:qr-scan')
        ->name('customer.scan');
});
