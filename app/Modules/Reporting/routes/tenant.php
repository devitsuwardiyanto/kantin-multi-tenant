<?php

use App\Models\Tenant;
use App\Modules\Reporting\Http\Controllers\TenantLedgerExportController;
use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Route portal tenant milik modul Reporting: panel keuangan (ringkasan ledger, rekonsiliasi,
 * permintaan penarikan) dan ekspor CSV ledger tenant aktif (TenantContext).
 */
PortalRoutes::tenant(function (): void {
    Route::get('/finance', fn (Tenant $tenant) => view('reporting::tenant.finance-panel', ['tenant' => $tenant]))
        ->name('finance');
    Route::get('/finance/ledger.csv', TenantLedgerExportController::class)->name('finance.export');
});
