<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\ModifierGroup;
use App\Models\Tenant;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\ResolveCustomerSession;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UC-03 Kelola Keranjang Multi-Tenant: pengelompokan per tenant + subtotal, pajak dan biaya
 * layanan per tenant, "Menu telah habis" (alur 2a), checkout nonaktif saat kosong (alur 6a),
 * keranjang bertahan ≥ 30 menit, dan menu bermodifier diteruskan ke formulir UC-04.
 */
class CartUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private function sessionFor(Canteen $canteen): CustomerSession
    {
        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();

        return $session;
    }

    private function bindSession(?CustomerSession $session): void
    {
        $this->app->instance(ResolveCustomerSession::class, new class($session) extends ResolveCustomerSession
        {
            public function __construct(private ?CustomerSession $stub) {}

            public function current(Request $request): ?CustomerSession
            {
                return $this->stub;
            }
        });
    }

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    public function test_cart_groups_lines_per_tenant_with_tax_and_service_fee(): void
    {
        $canteen = Canteen::factory()->create(['tax_rate' => 0.1000, 'service_fee_rate' => 0.0200]);
        $warung = Tenant::factory()->create(['canteen_id' => $canteen->id, 'display_name' => 'Warung Bu Rina']);
        $kopi = Tenant::factory()->create(['canteen_id' => $canteen->id, 'display_name' => 'Kopi Serambi']);
        $ayam = Menu::factory()->create(['tenant_id' => $warung->id, 'base_price' => 18000, 'stock_qty' => 10]);
        $esKopi = Menu::factory()->create(['tenant_id' => $kopi->id, 'base_price' => 18000, 'stock_qty' => 10]);
        $session = $this->sessionFor($canteen);

        app(CartService::class)->add($session, $ayam->id, 1);
        app(CartService::class)->add($session, $esKopi->id, 2);
        $view = app(CartService::class)->view($session);

        $groups = $view->tenantGroups();
        $this->assertCount(2, $groups);
        $this->assertSame([18000, 36000], array_column($groups, 'subtotal'));
        $this->assertSame(54000, $view->subtotal);
        $this->assertSame(5400, $view->taxAmount);
        $this->assertSame(1080, $view->serviceFeeAmount);
        $this->assertSame(60480, $view->grandTotal());

        $this->bindSession($session);
        Livewire::test('ordering::cart', ['canteenSlug' => $canteen->slug])
            ->assertSeeInOrder(['Warung Bu Rina', 'Subtotal Rp18.000', 'Kopi Serambi', 'Subtotal Rp36.000'])
            ->assertSeeInOrder(['Subtotal (2 tenant)', 'Rp54.000', 'Pajak', 'Rp5.400', 'Biaya layanan', 'Rp1.080', 'Total', 'Rp60.480']);
    }

    public function test_sold_out_menu_is_rejected_with_message(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'stock_qty' => 0]);
        $session = $this->sessionFor($canteen);
        $this->bindSession($session);

        Livewire::test('ordering::cart', ['canteenSlug' => $canteen->slug])
            ->call('add', $menu->id)
            ->assertSee('Menu telah habis.')
            ->assertSee('Keranjang kosong');
    }

    public function test_checkout_button_is_disabled_when_cart_becomes_empty(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'stock_qty' => 5]);
        $session = $this->sessionFor($canteen);
        app(CartService::class)->add($session, $menu->id, 1);
        $lineKey = app(CartService::class)->view($session)->lines[0]->lineKey;
        $this->bindSession($session);

        Livewire::test('ordering::cart', ['canteenSlug' => $canteen->slug])
            ->assertDontSeeHtml('disabled data-test="checkout-button"')
            ->call('decrement', $lineKey)
            ->assertSee('Keranjang kosong')
            ->assertSeeHtml('disabled data-test="checkout-button"');
    }

    public function test_cart_survives_reload_for_at_least_thirty_minutes(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'stock_qty' => 5]);
        $session = $this->sessionFor($canteen);
        $this->bindSession($session);

        Livewire::test('ordering::cart', ['canteenSlug' => $canteen->slug])->call('add', $menu->id);

        $this->assertGreaterThanOrEqual(1800, Redis::ttl('cart:'.$session->id));
        Livewire::test('ordering::cart', ['canteenSlug' => $canteen->slug])->assertSee($menu->name);
    }

    public function test_menu_with_active_modifier_group_opens_customization_form(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'stock_qty' => 5]);
        $group = ModifierGroup::factory()->create(['tenant_id' => $tenant->id, 'min_select' => 1]);
        $menu->modifierGroups()->attach($group->id, ['tenant_id' => $tenant->id, 'sort_order' => 1]);
        $session = $this->sessionFor($canteen);
        $this->bindSession($session);

        Livewire::test('ordering::cart', ['canteenSlug' => $canteen->slug])
            ->call('add', $menu->id)
            ->assertDispatched('customize-item', menuId: $menu->id)
            ->assertSee('Keranjang kosong');
    }
}
