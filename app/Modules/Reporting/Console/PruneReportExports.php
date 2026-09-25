<?php

namespace App\Modules\Reporting\Console;

use App\Models\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * UC-17: menghapus berkas ekspor yang tautannya sudah kedaluwarsa (24 jam).
 */
class PruneReportExports extends Command
{
    protected $signature = 'reporting:prune-exports';

    protected $description = 'Hapus berkas ekspor laporan yang sudah kedaluwarsa';

    public function handle(): int
    {
        $expired = ReportExport::query()->withoutGlobalScope('tenant')
            ->whereNotNull('path')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $export) {
            Storage::disk('local')->delete((string) $export->path);
            $export->forceFill(['path' => null, 'status' => 'expired'])->save();
        }

        $this->info($expired->count().' berkas ekspor kedaluwarsa dihapus.');

        return self::SUCCESS;
    }
}
