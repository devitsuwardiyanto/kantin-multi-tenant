<?php

use App\Models\Tenant;
use App\Modules\Reporting\Http\Controllers\DownloadReportExportController;
use App\Modules\Reporting\Http\Controllers\TenantLedgerExportController;
use App\Support\Routing\PortalRoutes;
use Illuminate\Support\Facades\Route;

/**
 * Route portal tenant milik modul Reporting: panel keuangan (ringkasan ledger), ekspor CSV
 * ledger, laporan penjualan + ekspor .xlsx/PDF (UC-16/UC-17; unduhan bertanda tangan 24 jam),
 * dan rekonsiliasi bagi hasil (UC-18). tenant_id selalu dari TenantContext.
 */
PortalRoutes::tenant(function (): void {
    Route::get('/finance', fn (Tenant $tenant) => view('reporting::tenant.finance-panel', ['tenant' => $tenant]))
        ->name('finance');
    Route::get('/finance/ledger.csv', TenantLedgerExportController::class)->name('finance.export');

    Route::get('/reports', fn (Tenant $tenant) => view('reporting::tenant.sales-report', ['tenant' => $tenant]))
        ->name('reports');
    Route::get('/reports/exports/{export}', DownloadReportExportController::class)
        ->whereNumber('export')
        ->middleware('signed')
        ->name('reports.download');

    Route::get('/reconciliation', fn (Tenant $tenant) => view('reporting::tenant.reconciliation', ['tenant' => $tenant]))
        ->name('reconciliation');
});
