<?php

namespace App\Modules\Reporting;

use App\Modules\ModuleServiceProvider;
use App\Modules\Reporting\Console\PruneReportExports;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Modul: Reporting (alias `reporting`).
 * Tanggung jawab: Laporan scoped, rekonsiliasi, ekspor async, withdrawal (Modul 13).
 *
 * Titik perakitan modul: binding container di register(); route (routes/*.php), view
 * (`reporting::`) dan komponen Livewire (`<livewire:reporting::...>`) dimuat oleh
 * ModuleServiceProvider::boot(). Batas antarmodul ditegakkan lewat kontrak & event,
 * bukan akses langsung tabel/controller modul lain.
 */
final class ReportingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Binding kontrak -> implementasi ditambahkan saat modul diimplementasikan.
    }

    public function boot(): void
    {
        parent::boot();

        // UC-17: berkas ekspor dihapus setelah tautannya kedaluwarsa (24 jam).
        if ($this->app->runningInConsole()) {
            $this->commands([PruneReportExports::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('reporting:prune-exports')->hourly()->withoutOverlapping();
        });
    }

    protected function moduleAlias(): string
    {
        return 'reporting';
    }
}
