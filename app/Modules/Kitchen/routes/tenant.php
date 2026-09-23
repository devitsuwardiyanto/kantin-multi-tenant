<?php

use App\Models\Tenant;
use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Route portal tenant milik modul Kitchen: Kitchen Display System (KDS) realtime yang merender
 * komponen <livewire:kitchen::kitchen-board>.
 */
PortalRoutes::tenant(function (): void {
    Route::get('/kitchen', fn (Tenant $tenant) => view('kitchen::tenant.kitchen-board', ['tenant' => $tenant]))
        ->name('kitchen');
});
