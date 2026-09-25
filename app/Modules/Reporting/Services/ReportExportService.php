<?php

namespace App\Modules\Reporting\Services;

use App\Models\ReportExport;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Reporting\Exceptions\ReportExportException;
use App\Modules\Reporting\Jobs\GenerateSalesReportExport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

/**
 * UC-17 Ekspor Laporan: memvalidasi permintaan (format, rentang ≤ 12 bulan), mencatatnya, lalu
 * mengantrikan pembuatan berkas (langkah 2). Volume besar (alur 2a) → berkas juga dikirim lewat
 * surel setelah selesai. Tautan unduh bertanda tangan dan berlaku 24 jam.
 *
 * @phpstan-import-type SalesDocument from \App\Modules\Reporting\Exports\SalesReportXlsx
 */
final class ReportExportService
{
    public const FORMATS = ['xlsx', 'pdf'];

    public const LINK_TTL_HOURS = 24;

    public function __construct(private TenantSalesReport $report) {}

    /** Jumlah transaksi di atas batas ini dianggap volume besar (alur 2a). */
    public static function mailThreshold(): int
    {
        return (int) config('services.report_export.mail_threshold', 5000);
    }

    /**
     * @throws ReportExportException
     */
    public function request(Tenant $tenant, User $user, string $format, CarbonImmutable $from, CarbonImmutable $to): ReportExport
    {
        if (! in_array($format, self::FORMATS, true)) {
            throw ReportExportException::unsupportedFormat();
        }
        if ($from->greaterThan($to)) {
            throw ReportExportException::invalidRange();
        }
        if ($from->addMonthsNoOverflow(12)->lessThanOrEqualTo($to)) {
            throw ReportExportException::rangeTooLong();
        }

        $count = $this->report->count((int) $tenant->id, $from, $to);

        $export = new ReportExport;
        $export->forceFill([
            'tenant_id' => $tenant->id,
            'requested_by' => $user->id,
            'format' => $format,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'status' => 'queued',
            'row_count' => $count,
            'notify_by_mail' => $count > self::mailThreshold(),
        ])->save();

        GenerateSalesReportExport::dispatch($export->id);

        return $export;
    }

    /**
     * Isi berkas: kop identitas tenant + ringkasan + baris transaksi sesuai penyaring aktif.
     *
     * @return SalesDocument
     */
    public function document(ReportExport $export): array
    {
        $tenant = Tenant::query()->with('canteen')->findOrFail($export->tenant_id);
        $from = $export->date_from->startOfDay();
        $to = $export->date_to->startOfDay();
        $summary = $this->report->summary((int) $tenant->id, $from, $to);

        return [
            'tenant' => (string) $tenant->display_name,
            'canteen' => (string) $tenant->canteen?->name,
            'period' => $from->settings(['locale' => 'id'])->translatedFormat('j M Y').' – '.$to->settings(['locale' => 'id'])->translatedFormat('j M Y'),
            'generated_at' => now()->setTimezone((string) config('app.display_timezone'))->format('Y-m-d H:i').' WIB',
            'summary' => [
                'revenue' => $summary['revenue'],
                'transactions' => $summary['transactions'],
                'average' => $summary['average'],
                'median' => $summary['median'],
                'top_menus' => $summary['top_menus'],
            ],
            'rows' => $this->report->rows((int) $tenant->id, $from, $to),
        ];
    }

    public function downloadUrl(ReportExport $export): ?string
    {
        if (! $export->isDownloadable()) {
            return null;
        }

        $slug = Tenant::query()->whereKey($export->tenant_id)->value('slug');

        return URL::temporarySignedRoute('tenant.reports.download', $export->expires_at, ['tenant' => $slug, 'export' => $export->id]);
    }

    public function filename(ReportExport $export): string
    {
        return 'laporan-penjualan-'.$export->date_from->toDateString().'_'.$export->date_to->toDateString().'.'.$export->format;
    }
}
