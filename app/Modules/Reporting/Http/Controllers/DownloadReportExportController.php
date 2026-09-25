<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ReportExport;
use App\Modules\Reporting\Services\ReportExportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UC-17 langkah 4: unduh berkas ekspor. Route bertanda tangan (kedaluwarsa 24 jam) DAN
 * dibatasi TenantContext — tautan tenant lain tetap 404 walau tanda tangannya sah.
 */
final class DownloadReportExportController extends Controller
{
    public function __invoke(Request $request, TenantContext $context, ReportExportService $service): StreamedResponse
    {
        $record = ReportExport::query()->where('tenant_id', $context->id())->findOrFail((int) $request->route('export'));
        abort_unless($record->isDownloadable(), 410, 'Tautan unduh sudah kedaluwarsa.');

        return Storage::disk('local')->download((string) $record->path, $service->filename($record));
    }
}
