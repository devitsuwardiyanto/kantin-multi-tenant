<?php

namespace App\Modules\Admin;

use Illuminate\Support\ServiceProvider;

/**
 * Modul: Admin.
 * Tanggung jawab: Administrasi kantin, tenant, role, komisi, rekening (Modul 5). Pemilik route admin.
 *
 * Titik perakitan modul (modular monolith): binding container di register(),
 * route/event/policy di boot(). Batas antarmodul ditegakkan lewat kontrak & event,
 * bukan akses langsung tabel/controller modul lain.
 */
final class AdminServiceProvider extends ServiceProvider
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
