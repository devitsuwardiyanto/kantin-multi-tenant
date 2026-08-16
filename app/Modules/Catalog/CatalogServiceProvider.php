<?php

namespace App\Modules\Catalog;

use Illuminate\Support\ServiceProvider;

/**
 * Modul: Catalog.
 * Tanggung jawab: Kategori, menu, modifier, stok tenant, dan public catalog (Modul 7).
 *
 * Titik perakitan modul (modular monolith): binding container di register(),
 * route/event/policy di boot(). Batas antarmodul ditegakkan lewat kontrak & event,
 * bukan akses langsung tabel/controller modul lain.
 */
final class CatalogServiceProvider extends ServiceProvider
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
