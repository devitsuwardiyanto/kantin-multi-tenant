<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Tenant;
use App\Modules\Catalog\Events\CatalogChanged;
use App\Modules\Catalog\Services\MenuStockService;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Temuan audit Pertemuan 14 (UC-01 mutu, UC-13 langkah 6, UC-14 langkah 3): perubahan katalog
 * disiarkan lewat WebSocket ke channel publik kantin, bukan hanya menunggu polling.
 */
class CatalogBroadcastTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Canteen, 1: Menu} */
    private function menu(int $stock = 10): array
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'stock_qty' => $stock, 'is_available' => true]);

        return [$canteen, $menu];
    }

    public function test_toggling_availability_broadcasts_to_canteen_catalog_channel(): void
    {
        [$canteen, $menu] = $this->menu();
        Event::fake([CatalogChanged::class]);

        app(MenuStockService::class)->toggleAvailability($menu);

        Event::assertDispatched(CatalogChanged::class, function (CatalogChanged $event) use ($canteen, $menu): bool {
            $channel = $event->broadcastOn()[0];

            return $event->canteenId === $canteen->id
                && $channel instanceof Channel && $channel->name === 'canteen.'.$canteen->id.'.catalog'
                && $event->broadcastWith() === ['menu_id' => $menu->id];
        });
    }

    public function test_only_customer_visible_changes_are_broadcast(): void
    {
        [, $menu] = $this->menu(stock: 5);
        Event::fake([CatalogChanged::class]);

        app(MenuStockService::class)->adjust($menu, -2, 'adjustment', 'uji-1');
        Event::assertNotDispatched(CatalogChanged::class);

        app(MenuStockService::class)->adjust($menu->fresh(), -3, 'adjustment', 'uji-2');
        Event::assertDispatchedTimes(CatalogChanged::class, 1);

        $menu->fresh()->forceFill(['base_price' => 21000])->save();
        Event::assertDispatchedTimes(CatalogChanged::class, 2);
    }

    public function test_checkout_that_sells_the_last_portion_broadcasts_sold_out(): void
    {
        [$canteen, $menu] = $this->menu(stock: 2);
        CommissionScheme::factory()->create(['tenant_id' => $menu->tenant_id, 'commission_rate' => 0.15, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        $session = new CustomerSession;
        $session->forceFill(['canteen_id' => $canteen->id, 'session_token_hash' => OpaqueToken::issue(32)['hash'], 'status' => 'active', 'expires_at' => now()->addHours(4)])->save();
        app(CartService::class)->add($session, $menu->id, 2);
        Event::fake([CatalogChanged::class]);

        app(CheckoutService::class)->checkout($session, (string) Str::uuid());

        Event::assertDispatched(CatalogChanged::class, fn (CatalogChanged $event): bool => $event->menuId === $menu->id);
        Redis::del('cart:'.$session->id);
    }

    public function test_customer_catalog_listens_on_canteen_channel_with_polling_fallback(): void
    {
        [$canteen] = $this->menu();

        $component = Livewire::test('catalog::menu-catalog', ['canteenSlug' => $canteen->slug]);

        $this->assertArrayHasKey('echo:canteen.'.$canteen->id.'.catalog,.CatalogChanged', $component->instance()->getListeners());
        $component->assertSeeHtml('wire:poll.15s');
    }
}
