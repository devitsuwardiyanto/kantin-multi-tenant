<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Services\TenantLedgerReport;
use App\Support\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor CSV ledger tenant AKTIF. tenant_id berasal dari TenantContext (di-set middleware),
 * bukan input klien — mencegah ekspor lintas tenant.
 */
final class TenantLedgerExportController extends Controller
{
    public function __invoke(TenantContext $context, TenantLedgerReport $report): StreamedResponse
    {
        $tenantId = $context->id();
        $rows = $report->ledgerRows($tenantId);

        $filename = 'ledger-tenant-'.$tenantId.'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['tanggal', 'tipe', 'available_delta', 'held_delta', 'order_id', 'payment_id', 'withdrawal_id']);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
