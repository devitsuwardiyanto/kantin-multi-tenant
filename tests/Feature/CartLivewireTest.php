<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Tenant;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\ResolveCustomerSession;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\TestCase;

class CartLivewireTest extends TestCase
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

    /**
     * Mengikat resolver agar mengembalikan sesi tertentu (menstubkan cookie tepercaya),
     * sehingga uji komponen fokus pada perilaku keranjang.
     */
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

    public function test_cart_without_session_shows_empty_state_and_add_is_noop(): void
    {
        $canteen = Canteen::factory()->create();
        $this->bindSession(null);

        Livewire::test('cart', ['canteenSlug' => $canteen->slug])
            ->call('add', 999)
            ->assertSee('Belum ada sesi');
    }

    public function test_add_event_adds_line_and_renders_subtotal(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => 12000, 'stock_qty' => 5]);
        $session = $this->sessionFor($canteen);
        $this->bindSession($session);

        Livewire::test('cart', ['canteenSlug' => $canteen->slug])
            ->call('add', $menu->id)
            ->assertSee($menu->name)
            ->assertSee('12.000');

        $this->assertSame(1, Redis::exists('cart:'.$session->id));
    }

    public function test_increment_and_remove_update_cart(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => 10000, 'stock_qty' => 20]);
        $session = $this->sessionFor($canteen);
        app(CartService::class)->add($session, $menu->id, 1);
        $lineKey = app(CartService::class)->view($session)->lines[0]->lineKey;
        $this->bindSession($session);

        Livewire::test('cart', ['canteenSlug' => $canteen->slug])
            ->call('increment', $lineKey)
            ->assertSee('20.000') // 2 x 10.000
            ->call('remove', $lineKey)
            ->assertSee('Keranjang kosong');
    }

    public function test_catalog_add_button_dispatches_cart_event(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id]);

        Livewire::test('menu-catalog', ['canteenSlug' => $canteen->slug])
            ->call('add', $menu->id)
            ->assertDispatched('cart-add', menuId: $menu->id);
    }
}
