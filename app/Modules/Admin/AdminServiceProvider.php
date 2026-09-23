<?php

namespace App\Modules\Admin;

use App\Modules\ModuleServiceProvider;

/**
 * Modul: Admin (alias `admin`).
 * Tanggung jawab: Administrasi kantin, tenant, role, komisi, rekening (Modul 5). Pemilik route admin.
 *
 * Titik perakitan modul: binding container di register(); route (routes/*.php), view
 * (`admin::`) dan komponen Livewire (`<livewire:admin::...>`) dimuat oleh
 * ModuleServiceProvider::boot(). Batas antarmodul ditegakkan lewat kontrak & event,
 * bukan akses langsung tabel/controller modul lain.
 */
final class AdminServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Binding kontrak -> implementasi ditambahkan saat modul diimplementasikan.
    }

    protected function moduleAlias(): string
    {
        return 'admin';
    }
}
