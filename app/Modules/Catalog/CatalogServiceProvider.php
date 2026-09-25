<?php

namespace App\Modules\Catalog;

use App\Models\Menu;
use App\Models\Tenant;
use App\Modules\Catalog\Events\CatalogChanged;
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

    public function boot(): void
    {
        parent::boot();

        // UC-13/UC-14: setiap perubahan menu yang terlihat pelanggan disiarkan ke katalog kantin.
        Menu::saved(function (Menu $menu): void {
            if ($menu->wasRecentlyCreated || $this->changesVisibleToCustomers($menu)) {
                $this->broadcastCatalogChange($menu);
            }
        });
        Menu::deleted(fn (Menu $menu) => $this->broadcastCatalogChange($menu));
        Menu::restored(fn (Menu $menu) => $this->broadcastCatalogChange($menu));
    }

    /**
     * Perubahan stok biasa (mis. tiap checkout) tidak disiarkan; hanya saat menu berubah
     * dapat/tidak dapat dipesan (stok melewati nol) atau data tampilan menu berubah.
     */
    private function changesVisibleToCustomers(Menu $menu): bool
    {
        if ($menu->wasChanged(['is_available', 'name', 'description', 'base_price', 'photo_path', 'category_id', 'prep_minutes'])) {
            return true;
        }

        return $menu->wasChanged('stock_qty')
            && ((int) $menu->getOriginal('stock_qty') > 0) !== ((int) $menu->stock_qty > 0);
    }

    private function broadcastCatalogChange(Menu $menu): void
    {
        $canteenId = Tenant::query()->whereKey($menu->tenant_id)->value('canteen_id');
        if ($canteenId !== null) {
            event(new CatalogChanged((int) $canteenId, (int) $menu->id));
        }
    }

    protected function moduleAlias(): string
    {
        return 'catalog';
    }
}
