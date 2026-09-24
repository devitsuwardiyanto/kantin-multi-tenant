<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Models\TenantOperatingHour;
use App\Models\TenantOrder;
use App\Modules\Kitchen\Services\WaitTimeEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UC-01 Telusuri Menu & Tenant (kelompok per tenant, cari nama tenant/kategori, chip kategori,
 * tenant tutup) dan UC-11 Hitung Estimasi Waktu Tunggu.
 */
class CatalogBrowseUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private function menu(Tenant $tenant, string $category, array $attrs = []): Menu
    {
        $cat = MenuCategory::query()->withoutGlobalScope('tenant')->where(['tenant_id' => $tenant->id, 'name' => $category])->first()
            ?? MenuCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => $category]);

        return Menu::factory()->create(array_merge(['tenant_id' => $tenant->id, 'category_id' => $cat->id, 'is_available' => true, 'stock_qty' => 10, 'prep_minutes' => 8], $attrs));
    }

    public function test_catalog_groups_by_tenant_and_filters_by_tenant_name_and_category(): void
    {
        $canteen = Canteen::factory()->create();
        $rina = Tenant::factory()->create(['canteen_id' => $canteen->id, 'display_name' => 'Warung Bu Rina']);
        $kopi = Tenant::factory()->create(['canteen_id' => $canteen->id, 'display_name' => 'Kopi Serambi']);
        $this->menu($rina, 'Makanan utama', ['name' => 'Nasi Ayam Bakar', 'description' => 'Ayam bakar kecap']);
        $this->menu($kopi, 'Minuman', ['name' => 'Es Kopi Susu']);

        Livewire::test('catalog::menu-catalog', ['canteenSlug' => $canteen->slug])
            ->assertSeeInOrder(['Warung Bu Rina', 'Nasi Ayam Bakar', 'Ayam bakar kecap', 'Kopi Serambi', 'Es Kopi Susu'])
            ->set('search', 'Serambi')->assertSee('Es Kopi Susu')->assertDontSee('Nasi Ayam Bakar')
            ->set('search', '')->call('filterCategory', 'Makanan utama')->assertSee('Nasi Ayam Bakar')->assertDontSee('Es Kopi Susu');
    }

    public function test_closed_tenant_shows_opening_time_and_items_are_not_sellable(): void
    {
        $canteen = Canteen::factory()->create();
        $geprek = Tenant::factory()->create(['canteen_id' => $canteen->id, 'display_name' => 'Geprek Juara']);
        foreach (range(0, 6) as $day) {
            (new TenantOperatingHour)->forceFill(['tenant_id' => $geprek->id, 'day_of_week' => $day, 'opens_at' => '10:00:00', 'closes_at' => '20:00:00'])->save();
        }
        $this->menu($geprek, 'Makanan utama', ['name' => 'Ayam Geprek']);
        $this->travelTo(Carbon::parse('2026-09-24 08:00', 'Asia/Jakarta'));

        Livewire::test('catalog::menu-catalog', ['canteenSlug' => $canteen->slug])
            ->assertSee('Tutup · Buka pukul 10.00')
            ->assertSeeInOrder(['data-sellable="0"', 'Ayam Geprek'], false);
    }

    public function test_wait_estimate_uses_active_queue_divided_by_capacity(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $this->menu($tenant, 'Makanan utama', ['prep_minutes' => 8]);
        $estimator = app(WaitTimeEstimator::class);

        // Alur 1a: tanpa antrean → hanya waktu siap item (8 mnt → 8–12).
        $this->assertSame(['low' => 8, 'high' => 12], $estimator->forTenant($tenant));

        // Dua pesanan aktif @10 mnt, kapasitas paralel 2 → antrean 10 mnt; + 8 mnt item = 18–27.
        $commission = CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.1, 'valid_from' => now()->subDay(), 'valid_to' => null]);
        $menuId = Menu::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->value('id');
        foreach (['accepted', 'preparing', 'ready'] as $i => $status) {
            $order = (new Order)->forceFill(['public_id' => (string) Str::uuid(), 'order_number' => 'ORD-'.$i, 'canteen_id' => $canteen->id,
                'checkout_key' => (string) Str::uuid(), 'tracking_token_hash' => random_bytes(32), 'status' => 'paid']);
            $order->save();
            $to = (new TenantOrder)->forceFill(['order_id' => $order->id, 'tenant_id' => $tenant->id, 'commission_id' => $commission->id, 'status' => $status,
                'commission_rate_snapshot' => 0.1, 'subtotal_amount' => 10000, 'commission_amount' => 1000, 'net_amount' => 9000]);
            $to->save();
            (new OrderItem)->forceFill(['tenant_id' => $tenant->id, 'tenant_order_id' => $to->id, 'menu_id' => $menuId, 'name_snapshot' => 'X',
                'unit_price_snapshot' => 10000, 'prep_minutes_snapshot' => 10, 'quantity' => 1, 'line_total' => 10000])->save();
        }

        $this->assertSame(10.0, $estimator->queueMinutes($tenant), 'status ready tidak dihitung');
        $this->assertSame(['low' => 18, 'high' => 27], $estimator->forTenant($tenant));
        $this->assertSame('± 18–27 mnt', $estimator->label($tenant));
    }
}
