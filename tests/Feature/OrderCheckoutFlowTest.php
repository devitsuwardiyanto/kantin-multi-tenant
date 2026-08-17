<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Tenant;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Ordering\Services\ResolveCustomerSession;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Canteen, 1: Menu, 2: CustomerSession} */
    private function scenario(): array
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.15, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => 12000, 'stock_qty' => 10]);

        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();

        return [$canteen, $menu, $session];
    }

    private function bindSession(CustomerSession $session): void
    {
        $this->app->instance(ResolveCustomerSession::class, new class($session) extends ResolveCustomerSession
        {
            public function __construct(private CustomerSession $stub) {}

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

    public function test_livewire_checkout_creates_order_and_redirects(): void
    {
        [$canteen, $menu, $session] = $this->scenario();
        app(CartService::class)->add($session, $menu->id, 2);
        $this->bindSession($session);

        Livewire::test('cart', ['canteenSlug' => $canteen->slug])
            ->call('checkout')
            ->assertRedirect(route('customer.order.show', ['canteen' => $canteen->slug], false));

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, Redis::exists('cart:'.$session->id));
    }

    public function test_order_status_page_renders_with_tracking_cookie(): void
    {
        [$canteen, $menu, $session] = $this->scenario();
        app(CartService::class)->add($session, $menu->id, 1);
        $result = app(CheckoutService::class)->checkout($session, 'flow-key-1');
        $plain = $result->trackingToken;
        $this->assertNotNull($plain);

        $response = $this->withUnencryptedCookie('order_tracking', $plain)
            ->get(route('customer.order.show', ['canteen' => $canteen->slug]));

        $response->assertOk();
        $response->assertSee($result->order->order_number);
    }

    public function test_order_status_page_generic_404_without_valid_cookie(): void
    {
        [$canteen] = $this->scenario();

        $this->get(route('customer.order.show', ['canteen' => $canteen->slug]))->assertNotFound();

        $this->withUnencryptedCookie('order_tracking', 'token-palsu')
            ->get(route('customer.order.show', ['canteen' => $canteen->slug]))
            ->assertNotFound();
    }
}
