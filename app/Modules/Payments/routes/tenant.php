<?php

use App\Models\Tenant;
use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Route portal tenant milik modul Payments: pengajuan penarikan dana (UC-20,
 * <livewire:payments::withdrawal-request>).
 */
PortalRoutes::tenant(function (): void {
    Route::get('/withdrawals', fn (Tenant $tenant) => view('payments::tenant.withdrawals', ['tenant' => $tenant]))
        ->name('withdrawals');
});
