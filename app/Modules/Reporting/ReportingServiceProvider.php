<?php

namespace App\Modules\Reporting;

use Illuminate\Support\ServiceProvider;

/**
 * Modul: Reporting.
 * Tanggung jawab: Laporan scoped, rekonsiliasi, ekspor async, withdrawal (Modul 13).
 *
 * Titik perakitan modul (modular monolith): binding container di register(),
 * route/event/policy di boot(). Batas antarmodul ditegakkan lewat kontrak & event,
 * bukan akses langsung tabel/controller modul lain.
 */
final class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Binding kontrak -> implementasi ditambahkan saat modul diimplementasikan.
    }

    public function boot(): void
    {
        //
    }
}
