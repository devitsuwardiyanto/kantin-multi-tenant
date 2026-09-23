<?php

use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Route portal admin milik modul Payments: peninjauan penarikan dana tenant
 * (<livewire:payments::withdrawal-review>).
 */
PortalRoutes::admin(function (): void {
    Route::get('/withdrawals', fn () => view('payments::admin.withdrawals'))->name('withdrawals.index');
});
