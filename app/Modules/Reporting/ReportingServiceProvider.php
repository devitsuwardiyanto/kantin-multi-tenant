<?php

namespace App\Modules\Reporting;

use App\Modules\ModuleServiceProvider;

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

    protected function moduleAlias(): string
    {
        return 'reporting';
    }
}
