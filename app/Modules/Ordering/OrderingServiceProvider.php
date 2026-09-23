<?php

namespace App\Modules\Ordering;

use App\Modules\ModuleServiceProvider;

/**
 * Modul: Ordering (alias `ordering`).
 * Tanggung jawab: Keranjang (Redis), checkout atomik, order induk, snapshot (Modul 8-9). Pemilik route customer.
 *
 * Titik perakitan modul: binding container di register(); route (routes/*.php), view
 * (`ordering::`) dan komponen Livewire (`<livewire:ordering::...>`) dimuat oleh
 * ModuleServiceProvider::boot(). Batas antarmodul ditegakkan lewat kontrak & event,
 * bukan akses langsung tabel/controller modul lain.
 */
final class OrderingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Binding kontrak -> implementasi ditambahkan saat modul diimplementasikan.
    }

    protected function moduleAlias(): string
    {
        return 'ordering';
    }
}
