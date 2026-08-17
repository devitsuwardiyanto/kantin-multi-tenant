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
use App\Modules\Ordering\Services\ResolveTrackedOrder;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentLivewireTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Canteen, 1: Order} */
    private function placeOrder(): array
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.15, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => 20000, 'stock_qty' => 50]);

        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();

        app(CartService::class)->add($session, $menu->id, 1);
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;

        return [$canteen, $order];
    }

    private function bindOrder(Order $order): void
    {
        $this->app->instance(ResolveTrackedOrder::class, new class($order->id) extends ResolveTrackedOrder
        {
            public function __construct(private int $id) {}

            public function current(Request $request): ?Order
            {
                return Order::query()->find($this->id); // selalu segar (atribut + relasi)
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

    public function test_initiate_then_simulate_pay_marks_order_paid(): void
    {
        [$canteen, $order] = $this->placeOrder();
        $this->bindOrder($order);

        Livewire::test('order-payment', ['canteenSlug' => $canteen->slug])
            ->call('initiate')
            ->assertSee('5303360')           // payload QRIS tampil
            ->assertSee('Simulasi Bayar')    // tombol sandbox
            ->call('simulatePay')
            ->assertSee('Pembayaran berhasil');

        $this->assertSame('paid', $order->fresh()->status);
    }
}
