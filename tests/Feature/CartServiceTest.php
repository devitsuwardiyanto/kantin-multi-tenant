<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Tenant;
use App\Modules\Ordering\Exceptions\CartException;
use App\Modules\Ordering\Services\CartService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private function cart(): CartService
    {
        return app(CartService::class);
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

    /** @return array{0: Canteen, 1: Tenant, 2: Menu} */
    private function canteenWithMenu(int $price = 10000, int $stock = 50): array
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $menu = Menu::factory()->create([
            'tenant_id' => $tenant->id,
            'base_price' => $price,
            'stock_qty' => $stock,
            'is_available' => true,
        ]);

        return [$canteen, $tenant, $menu];
    }

    protected function tearDown(): void
    {
        // Bersihkan hanya key milik sesi uji (tak pernah FLUSHDB pada Redis bersama).
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    public function test_add_stores_line_in_redis_and_merges_identical_config(): void
    {
        [$canteen, , $menu] = $this->canteenWithMenu();
        $session = $this->sessionFor($canteen);

        $this->cart()->add($session, $menu->id, 2);
        $this->cart()->add($session, $menu->id, 3);

        // Bukti benar-benar tersimpan di Redis dengan key ter-scope sesi.
        $this->assertSame(1, Redis::exists('cart:'.$session->id));

        $view = $this->cart()->view($session);
        $this->assertCount(1, $view->lines, 'konfigurasi identik harus digabung menjadi satu baris');
        $this->assertSame(5, $view->lines[0]->quantity);
        $this->assertSame(50000, $view->subtotal);
    }

    public function test_add_rejects_menu_from_another_canteen(): void
    {
        [, , $menuA] = $this->canteenWithMenu();
        $otherCanteen = Canteen::factory()->create();
        $sessionOther = $this->sessionFor($otherCanteen);

        $this->expectException(CartException::class);
        $this->cart()->add($sessionOther, $menuA->id, 1);
    }

    public function test_add_rejects_unavailable_or_out_of_stock_menu(): void
    {
        [$canteen, , $menu] = $this->canteenWithMenu();
        $session = $this->sessionFor($canteen);

        $menu->update(['is_available' => false]);
        try {
            $this->cart()->add($session, $menu->id, 1);
            $this->fail('menu tidak tersedia seharusnya ditolak');
        } catch (CartException) {
            // diharapkan
        }

        $menu->update(['is_available' => true, 'stock_qty' => 0]);
        $this->expectException(CartException::class);
        $this->cart()->add($session, $menu->id, 1);
    }

    public function test_view_recomputes_price_from_database_and_flags_change(): void
    {
        [$canteen, , $menu] = $this->canteenWithMenu(price: 10000);
        $session = $this->sessionFor($canteen);
        $this->cart()->add($session, $menu->id, 2);

        // Harga dinaikkan di DB SETELAH masuk keranjang.
        $menu->update(['base_price' => 15000]);

        $view = $this->cart()->view($session);
        $line = $view->lines[0];
        $this->assertTrue($line->available);
        $this->assertTrue($line->priceChanged());
        $this->assertSame(15000, $line->unitPrice, 'harga otoritatif harus dari DB, bukan keranjang');
        $this->assertSame(30000, $view->subtotal);
    }

    public function test_view_flags_insufficient_stock_and_excludes_from_subtotal(): void
    {
        [$canteen, , $menu] = $this->canteenWithMenu(stock: 10);
        $session = $this->sessionFor($canteen);
        $this->cart()->add($session, $menu->id, 3);

        $menu->update(['stock_qty' => 1]);

        $view = $this->cart()->view($session);
        $this->assertFalse($view->lines[0]->available);
        $this->assertContains('insufficient_stock', $view->lines[0]->issues);
        $this->assertSame(0, $view->subtotal, 'baris bermasalah tak boleh ditagih');
        $this->assertTrue($view->hasBlockingIssues);
        $this->assertFalse($view->isOrderable());
    }

    public function test_view_flags_menu_unavailable_when_tenant_suspended(): void
    {
        [$canteen, $tenant, $menu] = $this->canteenWithMenu();
        $session = $this->sessionFor($canteen);
        $this->cart()->add($session, $menu->id, 1);

        $tenant->update(['status' => 'suspended']);

        $view = $this->cart()->view($session);
        $this->assertFalse($view->lines[0]->available);
        $this->assertContains('menu_unavailable', $view->lines[0]->issues);
    }

    public function test_set_quantity_zero_removes_and_clear_empties(): void
    {
        [$canteen, , $menu] = $this->canteenWithMenu();
        $session = $this->sessionFor($canteen);
        $this->cart()->add($session, $menu->id, 2);
        $lineKey = $this->cart()->view($session)->lines[0]->lineKey;

        $this->cart()->setQuantity($session, $lineKey, 0);
        $this->assertTrue($this->cart()->view($session)->isEmpty());
        $this->assertSame(0, Redis::exists('cart:'.$session->id), 'key kosong harus dihapus');

        $this->cart()->add($session, $menu->id, 1);
        $this->cart()->clear($session);
        $this->assertTrue($this->cart()->view($session)->isEmpty());
    }

    public function test_cart_is_isolated_per_session(): void
    {
        [$canteen, , $menu] = $this->canteenWithMenu();
        $sessionA = $this->sessionFor($canteen);
        $sessionB = $this->sessionFor($canteen);

        $this->cart()->add($sessionA, $menu->id, 2);

        $this->assertCount(1, $this->cart()->view($sessionA)->lines);
        $this->assertTrue($this->cart()->view($sessionB)->isEmpty(), 'keranjang sesi lain tidak bocor');
    }

    public function test_valid_modifier_prices_line_and_cross_tenant_modifier_rejected(): void
    {
        [$canteen, $tenant, $menu] = $this->canteenWithMenu(price: 12000);
        $session = $this->sessionFor($canteen);

        $group = ModifierGroup::factory()->create(['tenant_id' => $tenant->id]);
        $option = ModifierOption::factory()->create([
            'tenant_id' => $tenant->id,
            'group_id' => $group->id,
            'price_delta' => 3000,
            'is_available' => true,
        ]);

        $this->cart()->add($session, $menu->id, 1, [$option->id]);
        $line = $this->cart()->view($session)->lines[0];
        $this->assertSame(3000, $line->modifierTotal);
        $this->assertSame(15000, $line->lineTotal);

        // Modifier milik tenant lain harus ditolak.
        $otherTenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $foreignOption = ModifierOption::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->expectException(CartException::class);
        $this->cart()->add($session, $menu->id, 1, [$foreignOption->id]);
    }
}
