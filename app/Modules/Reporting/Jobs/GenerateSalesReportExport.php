<?php

namespace App\Modules\Reporting\Jobs;

use App\Models\ReportExport;
use App\Modules\Reporting\Exports\SalesReportPdf;
use App\Modules\Reporting\Exports\SalesReportXlsx;
use App\Modules\Reporting\Notifications\ReportExportReady;
use App\Modules\Reporting\Services\ReportExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * UC-17 langkah 2–4: membuat berkas .xlsx/.pdf di disk privat, menandai siap (tautan 24 jam),
 * dan mengirim surel bila volume besar (alur 2a). Gagal → status failed (tampil di layar).
 */
class GenerateSalesReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $exportId)
    {
        $this->afterCommit();
    }

    public function handle(ReportExportService $service, SalesReportXlsx $xlsx, SalesReportPdf $pdf): void
    {
        $export = ReportExport::query()->withoutGlobalScope('tenant')->find($this->exportId);
        if ($export === null || $export->status !== 'queued') {
            return;
        }

        $document = $service->document($export);
        $bytes = $export->format === 'pdf' ? $pdf->render($document) : $xlsx->render($document);
        $path = 'exports/tenant-'.$export->tenant_id.'/'.Str::uuid().'.'.$export->format;
        Storage::disk('local')->put($path, $bytes);

        $export->forceFill([
            'status' => 'ready',
            'path' => $path,
            'expires_at' => now()->addHours(ReportExportService::LINK_TTL_HOURS),
        ])->save();

        if ($export->notify_by_mail) {
            $export->requester?->notify(new ReportExportReady($export));
        }
    }

    public function failed(?Throwable $exception): void
    {
        ReportExport::query()->withoutGlobalScope('tenant')->whereKey($this->exportId)->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
