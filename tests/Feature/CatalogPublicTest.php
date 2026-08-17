<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Tenant;
use App\Modules\Catalog\Services\PublicCatalogQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogPublicTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Canteen, 1: Tenant} */
    private function canteenWithTenant(string $status = 'active'): array
    {
        $canteen = Canteen::factory()->create(['status' => 'active']);
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id, 'status' => $status]);

        return [$canteen, $tenant];
    }

    private function menu(Tenant $tenant, array $attrs = []): Menu
    {
        $category = MenuCategory::factory()->create(['tenant_id' => $tenant->id]);

        return Menu::factory()->create(array_merge([
            'tenant_id' => $tenant->id, 'category_id' => $category->id,
            'is_available' => true, 'stock_qty' => 10,
        ], $attrs));
    }

    public function test_catalog_shows_only_own_canteen_available_menus(): void
    {
        [$canteen, $tenant] = $this->canteenWithTenant();
        $this->menu($tenant, ['name' => 'Nasi Goreng']);
        $this->menu($tenant, ['name' => 'Habis', 'is_available' => false]);
        $this->menu($tenant, ['name' => 'Kosong', 'stock_qty' => 0]);

        // canteen lain tidak boleh muncul
        [, $otherTenant] = $this->canteenWithTenant();
        $this->menu($otherTenant, ['name' => 'Menu Kantin Lain']);

        Livewire::test('menu-catalog', ['canteenSlug' => $canteen->slug])
            ->assertSee('Nasi Goreng')
            ->assertDontSee('Habis')
            ->assertDontSee('Kosong')
            ->assertDontSee('Menu Kantin Lain');
    }

    public function test_search_filters_catalog(): void
    {
        [$canteen, $tenant] = $this->canteenWithTenant();
        $this->menu($tenant, ['name' => 'Ayam Bakar']);
        $this->menu($tenant, ['name' => 'Es Teh']);

        Livewire::test('menu-catalog', ['canteenSlug' => $canteen->slug])
            ->set('search', 'Ayam')
            ->assertSee('Ayam Bakar')
            ->assertDontSee('Es Teh');
    }

    public function test_public_catalog_query_is_free_of_n_plus_one(): void
    {
        [$canteen, $tenant] = $this->canteenWithTenant();
        for ($i = 0; $i < 20; $i++) {
            $this->menu($tenant, ['name' => "Menu {$i}"]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $menus = app(PublicCatalogQuery::class)->browse($canteen);
        $menus->each(function (Menu $menu): void {
            // akses relasi yang di-eager load — tidak boleh memicu query tambahan
            $menu->tenant->display_name;
            $menu->category?->name;
        });
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        // paginator: hitung total + halaman + eager tenant + eager category (+ menu_modifier orderBy) — jumlah tetap kecil
        $this->assertLessThanOrEqual(6, $count, "Query terlalu banyak ({$count}) — indikasi N+1");
    }

    public function test_customer_landing_page_renders(): void
    {
        [$canteen] = $this->canteenWithTenant();
        $this->get(route('customer.home', ['canteen' => $canteen->slug]))->assertOk();
    }
}
