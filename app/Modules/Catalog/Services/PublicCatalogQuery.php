<?php

namespace App\Modules\Catalog\Services;

use App\Models\Canteen;
use App\Models\Menu;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Query lintas-tenant yang SAH untuk katalog publik satu kantin. Bypass global scope
 * dilakukan eksplisit lalu diganti filter pengganti: hanya tenant milik canteen tsb,
 * berstatus aktif, dan menu yang sellable. Ditempatkan di class khusus agar mudah diaudit.
 */
final class PublicCatalogQuery
{
    /** @return Collection<int, Menu> */
    public function forCanteen(Canteen $canteen): Collection
    {
        return Menu::query()
            ->withoutGlobalScope('tenant')
            ->where('is_available', true)
            ->whereHas('tenant', function ($query) use ($canteen): void {
                $query->where('canteen_id', $canteen->id)->where('status', 'active');
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Katalog publik paginated + searchable + filter tenant. Eager load tenant/category
     * (jumlah query tetap, anti N+1). Bypass scope diganti filter canteen/status/sellable.
     *
     * @return LengthAwarePaginator<int, Menu>
     */
    public function browse(Canteen $canteen, string $search = '', ?int $tenantId = null): LengthAwarePaginator
    {
        return Menu::query()
            ->withoutGlobalScope('tenant')
            ->sellable()
            ->whereHas('tenant', function ($query) use ($canteen): void {
                $query->where('canteen_id', $canteen->id)->where('status', 'active');
            })
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->with(['tenant:id,display_name', 'category:id,name'])
            ->orderBy('name')
            ->paginate(16)
            ->withQueryString();
    }

    /** @return SupportCollection<int, Tenant> */
    public function activeTenants(Canteen $canteen): SupportCollection
    {
        return Tenant::query()
            ->where('canteen_id', $canteen->id)
            ->where('status', 'active')
            ->orderBy('display_name')
            ->get(['id', 'display_name']);
    }
}
