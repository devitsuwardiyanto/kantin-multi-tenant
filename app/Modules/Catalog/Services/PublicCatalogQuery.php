<?php

namespace App\Modules\Catalog\Services;

use App\Models\Canteen;
use App\Models\Menu;
use Illuminate\Support\Collection;

/**
 * Query lintas-tenant yang SAH untuk katalog publik satu kantin. Bypass global scope
 * dilakukan eksplisit lalu diganti filter pengganti: hanya tenant milik canteen tsb,
 * berstatus aktif, dan menu yang tersedia. Ditempatkan di class khusus agar mudah diaudit.
 */
final class PublicCatalogQuery
{
    /** @return Collection<int, Menu> */
    public function forCanteen(Canteen $canteen): Collection
    {
        return Menu::query()
            ->withoutGlobalScope('tenant') // bypass eksplisit, bukan diam-diam
            ->where('is_available', true)
            ->whereHas('tenant', function ($query) use ($canteen): void {
                $query->where('canteen_id', $canteen->id)->where('status', 'active');
            })
            ->orderBy('name')
            ->get();
    }
}
