<?php

use App\Models\Tenant;
use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Route portal tenant milik modul Ordering. UC-06 prasyarat 2: tenant mengaktifkan pre-order
 * dan mengatur kapasitas slot lewat <livewire:ordering::pre-order-settings>.
 */
PortalRoutes::tenant(function (): void {
    Route::get('/pre-order', fn (Tenant $tenant) => view('ordering::tenant.pre-order-settings', ['tenant' => $tenant]))
        ->name('pre-order-settings');
});
