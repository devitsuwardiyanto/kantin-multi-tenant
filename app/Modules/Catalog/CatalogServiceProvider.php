<?php

namespace App\Modules\Catalog;

use App\Modules\ModuleServiceProvider;

/**
 * Modul: Catalog (alias `catalog`).
 * Tanggung jawab: Kategori, menu, modifier, stok tenant, dan public catalog (Modul 7).
 *
 * Titik perakitan modul: binding container di register(); route (routes/*.php), view
 * (`catalog::`) dan komponen Livewire (`<livewire:catalog::...>`) dimuat oleh
 * ModuleServiceProvider::boot(). Batas antarmodul ditegakkan lewat kontrak & event,
 * bukan akses langsung tabel/controller modul lain.
 */
final class CatalogServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Binding kontrak -> implementasi ditambahkan saat modul diimplementasikan.
    }

    protected function moduleAlias(): string
    {
        return 'catalog';
    }
}
