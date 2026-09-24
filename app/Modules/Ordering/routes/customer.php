<?php

use App\Modules\Ordering\Http\Controllers\CustomerWelcomeController;
use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Route pelanggan milik modul Ordering (prefix kantin/{canteen}, name customer.*).
 * UC-02: halaman sambutan sesudah pindai QR meja.
 */
PortalRoutes::customer(function (): void {
    Route::get('/meja', CustomerWelcomeController::class)->name('welcome');
});
