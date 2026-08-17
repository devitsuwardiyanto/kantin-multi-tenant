<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\Tenant;
use App\Modules\Ordering\Exceptions\CheckoutException;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private function cart(): CartService
    {
        return app(CartService::class);
    }

    private function checkout(): CheckoutService
    {
        return app(CheckoutService::class);
    }

    private function sessionFor(Canteen $canteen): CustomerSession
    {
        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $canteen->id,
            'dining_table_id' => null,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();

        return $session;
    }

    private function tenantWithCommission(Canteen $canteen, float $rate = 0.15): Tenant
    {
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        CommissionScheme::factory()->create([
            'tenant_id' => $tenant->id,
            'commission_rate' => $rate,
            'valid_from' => now()->subMonth(),
            'valid_to' => null,
        ]);

        return $tenant;
    }

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    public function test_checkout_creates_order_with_snapshots_totals_and_clears_cart(): void
    {
        $canteen = Canteen::factory()->create(['tax_rate' => 0.1000, 'service_fee_rate' => 0.0200]);
        $tenant = $this->tenantWithCommission($canteen, 0.15);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => 25000, 'stock_qty' => 10, 'prep_minutes' => 8]);
        $session = $this->sessionFor($canteen);
        $this->cart()->add($session, $menu->id, 2); // subtotal 50000

        $result = $this->checkout()->checkout($session, (string) Str::uuid());
        $order = $result->order;

        // Total order
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertSame(50000, $order->subtotal_amount);
        $this->assertSame(5000, $order->tax_amount);        // 10%
        $this->assertSame(1000, $order->service_fee_amount); // 2%
        $this->assertSame(56000, $order->grand_total_amount);
        $this->assertNotNull($result->trackingToken);
        $this->assertFalse($result->isReplay);

        // tenant_order: komisi & net
        $tenantOrder = $order->tenantOrders()->firstOrFail();
        $this->assertSame(7500, $tenantOrder->commission_amount); // 15% dari 50000
        $this->assertSame(42500, $tenantOrder->net_amount);
        $this->assertSame('0.1500', $tenantOrder->commission_rate_snapshot);

        // order_item snapshot
        $item = $tenantOrder->items()->firstOrFail();
        $this->assertSame($menu->id, $item->menu_id);
        $this->assertSame($menu->name, $item->name_snapshot);
        $this->assertSame(25000, $item->unit_price_snapshot);
        $this->assertSame(8, $item->prep_minutes_snapshot);
        $this->assertSame(2, $item->quantity);
        $this->assertSame(50000, $item->line_total);

        // stok dipotong & keranjang dikosongkan
        $menu->refresh();
        $this->assertSame(8, $menu->stock_qty);
        $this->assertSame(0, Redis::exists('cart:'.$session->id));
        $this->assertSame(1, DB::table('menu_stock_movements')->where('menu_id', $menu->id)->where('type', 'sale')->count());
    }

    public function test_checkout_splits_across_tenants(): void
    {
        $canteen = Canteen::factory()->create(['tax_rate' => 0.1000, 'service_fee_rate' => 0.0200]);
        $tenantA = $this->tenantWithCommission($canteen, 0.15);
        $tenantB = $this->tenantWithCommission($canteen, 0.20);
        $menuA = Menu::factory()->create(['tenant_id' => $tenantA->id, 'base_price' => 20000, 'stock_qty' => 10]);
        $menuB = Menu::factory()->create(['tenant_id' => $tenantB->id, 'base_price' => 30000, 'stock_qty' => 10]);
        $session = $this->sessionFor($canteen);
        $this->cart()->add($session, $menuA->id, 1); // 20000
        $this->cart()->add($session, $menuB->id, 1); // 30000

        $order = $this->checkout()->checkout($session, (string) Str::uuid())->order;

        $this->assertSame(2, $order->tenantOrders()->count());
        $this->assertSame(50000, $order->subtotal_amount);

        $toA = $order->tenantOrders()->where('tenant_id', $tenantA->id)->firstOrFail();
        $toB = $order->tenantOrders()->where('tenant_id', $tenantB->id)->firstOrFail();
        $this->assertSame(3000, $toA->commission_amount);  // 15% x 20000
        $this->assertSame(6000, $toB->commission_amount);  // 20% x 30000
    }

    public function test_checkout_is_idempotent(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = $this->tenantWithCommission($canteen);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => 10000, 'stock_qty' => 10]);
        $session = $this->sessionFor($canteen);
        $this->cart()->add($session, $menu->id, 3);
        $key = (string) Str::uuid();

        $first = $this->checkout()->checkout($session, $key);
        // klik ganda: keranjang sudah dikosongkan, tapi key sama harus kembalikan order yang sama
        $second = $this->checkout()->checkout($session, $key);

        $this->assertSame($first->order->id, $second->order->id);
        $this->assertTrue($second->isReplay);
        $this->assertSame(1, Order::query()->count());
        $menu->refresh();
        $this->assertSame(7, $menu->stock_qty, 'stok hanya dipotong sekali');
    }

    public function test_checkout_atomic_rollback_when_combined_stock_exceeds_available(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = $this->tenantWithCommission($canteen);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => 10000, 'stock_qty' => 5]);
        $group = ModifierGroup::factory()->create(['tenant_id' => $tenant->id]);
        $optA = ModifierOption::factory()->create(['tenant_id' => $tenant->id, 'group_id' => $group->id, 'price_delta' => 0]);
        $optB = ModifierOption::factory()->create(['tenant_id' => $tenant->id, 'group_id' => $group->id, 'price_delta' => 0]);
        $session = $this->sessionFor($canteen);

        // Dua baris menu sama (modifier beda): masing-masing 3, tiap baris lolos revalidasi (3<=5),
        // tetapi gabungan 6 > 5 → guard atomik di reserveStock gagal → rollback penuh.
        $this->cart()->add($session, $menu->id, 3, [$optA->id]);
        $this->cart()->add($session, $menu->id, 3, [$optB->id]);

        try {
            $this->checkout()->checkout($session, (string) Str::uuid());
            $this->fail('checkout seharusnya gagal karena stok gabungan melebihi tersedia');
        } catch (CheckoutException) {
            // diharapkan
        }

        $this->assertSame(0, Order::query()->count(), 'tidak boleh ada order parsial');
        $menu->refresh();
        $this->assertSame(5, $menu->stock_qty, 'stok harus utuh setelah rollback');
        $this->assertSame(0, DB::table('menu_stock_movements')->count());
    }

    public function test_checkout_rejects_empty_and_blocking_cart(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = $this->tenantWithCommission($canteen);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'stock_qty' => 5]);
        $session = $this->sessionFor($canteen);

        // kosong
        try {
            $this->checkout()->checkout($session, (string) Str::uuid());
            $this->fail('kosong harus ditolak');
        } catch (CheckoutException) {
        }

        // ada item lalu menu dinonaktifkan → blocking
        $this->cart()->add($session, $menu->id, 1);
        $menu->update(['is_available' => false]);
        $this->expectException(CheckoutException::class);
        $this->checkout()->checkout($session, (string) Str::uuid());
    }

    public function test_checkout_uses_active_commission_scheme(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        // skema lama (ditutup) + skema aktif baru
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.30, 'valid_from' => now()->subYear(), 'valid_to' => now()->subMonth()]);
        $active = CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.10, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => 40000, 'stock_qty' => 10]);
        $session = $this->sessionFor($canteen);
        $this->cart()->add($session, $menu->id, 1);

        $order = $this->checkout()->checkout($session, (string) Str::uuid())->order;
        $tenantOrder = $order->tenantOrders()->firstOrFail();

        $this->assertSame($active->id, $tenantOrder->commission_id);
        $this->assertSame(4000, $tenantOrder->commission_amount); // 10% aktif, bukan 30% lama
    }

    public function test_checkout_pre_order_sets_scheduled_at(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = $this->tenantWithCommission($canteen);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'stock_qty' => 5]);
        $session = $this->sessionFor($canteen);
        $this->cart()->add($session, $menu->id, 1);
        $when = now()->addDay()->startOfHour();

        $order = $this->checkout()->checkout($session, (string) Str::uuid(), $when)->order;

        $tenantOrder = $order->tenantOrders()->firstOrFail();
        $this->assertNotNull($tenantOrder->scheduled_at);
        $this->assertSame($when->timestamp, $tenantOrder->scheduled_at->timestamp);
    }
}
