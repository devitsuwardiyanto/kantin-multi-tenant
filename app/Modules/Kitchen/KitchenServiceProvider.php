<?php

namespace App\Modules\Kitchen;

use Illuminate\Support\ServiceProvider;

/**
 * Modul: Kitchen.
 * Tanggung jawab: Kitchen Display System realtime, state machine order, notifikasi (Modul 12).
 *
 * Titik perakitan modul (modular monolith): binding container di register(),
 * route/event/policy di boot(). Batas antarmodul ditegakkan lewat kontrak & event,
 * bukan akses langsung tabel/controller modul lain.
 */
final class KitchenServiceProvider extends ServiceProvider
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
