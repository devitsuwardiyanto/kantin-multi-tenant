<?php

use App\Modules\Ordering\Http\Controllers\CustomerWelcomeController;
use App\Modules\Ordering\Http\Controllers\OrderStatusController;
use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Route pelanggan milik modul Ordering (prefix kantin/{canteen}, name customer.*).
 * UC-02: halaman sambutan sesudah pindai QR meja. UC-05/UC-06: halaman checkout (komponen
 * <livewire:ordering::checkout>). Status pesanan pasca-checkout dikenali lewat cookie pelacakan
 * opaque (order_tracking), bukan ID pesanan di URL.
 */
PortalRoutes::customer(function (): void {
    Route::get('/meja', CustomerWelcomeController::class)->name('welcome');
    Route::get('/checkout', fn (string $canteen) => view('ordering::checkout', ['canteen' => $canteen]))->name('checkout');
    Route::get('/order', OrderStatusController::class)->name('order.show');
});
