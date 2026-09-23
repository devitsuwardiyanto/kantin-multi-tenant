<?php

use App\Modules\Ordering\Http\Controllers\OrderStatusController;
use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Route portal pelanggan milik modul Ordering. Status pesanan pasca-checkout dikenali lewat
 * cookie pelacakan opaque (order_tracking), bukan ID pesanan di URL.
 */
PortalRoutes::customer(function (): void {
    Route::get('/order', OrderStatusController::class)->name('order.show');
});
