<?php

namespace App\Modules\Kitchen;

use App\Modules\ModuleServiceProvider;

/**
 * Modul: Kitchen (alias `kitchen`).
 * Tanggung jawab: Kitchen Display System realtime, state machine order, notifikasi (Modul 12).
 *
 * Titik perakitan modul: binding container di register(); route (routes/*.php), view
 * (`kitchen::`) dan komponen Livewire (`<livewire:kitchen::...>`) dimuat oleh
 * ModuleServiceProvider::boot(). Batas antarmodul ditegakkan lewat kontrak & event,
 * bukan akses langsung tabel/controller modul lain.
 */
final class KitchenServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Binding kontrak -> implementasi ditambahkan saat modul diimplementasikan.
    }

    protected function moduleAlias(): string
    {
        return 'kitchen';
    }
}
