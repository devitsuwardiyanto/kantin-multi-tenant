<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantOrder;
use App\Modules\Kitchen\Services\KitchenService;
use App\Modules\Ordering\Events\OrderTrackingUpdated;
use App\Modules\Ordering\Realtime\CustomerChannelUser;
use App\Modules\Ordering\Realtime\OrderChannels;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Ordering\Services\ResolveTrackedOrder;
use App\Modules\Payments\Services\PaymentService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UC-09 Lacak Status Pesanan: status tiap sub-pesanan (Diterima → Dimasak → Siap → Selesai) +
 * estimasi (UC-11), channel privat `order.{public_id}` yang hanya dapat didengar pemilik sesi
 * anonim dengan token pelacakan sah, siaran tanpa ID internal, dan polling cadangan 15 detik.
 */
class OrderTrackingUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private Canteen $canteen;

    private CustomerSession $session;

    private Order $order;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->canteen = Canteen::factory()->create();
        foreach (['Warung Bu Rina' => 18000, 'Kopi Serambi' => 18000] as $name => $price) {
            $tenant = Tenant::factory()->create(['canteen_id' => $this->canteen->id, 'display_name' => $name]);
            CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.10, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
            Menu::factory()->create(['tenant_id' => $tenant->id, 'name' => $name === 'Kopi Serambi' ? 'Es Kopi Susu' : 'Nasi Ayam Bakar', 'base_price' => $price, 'stock_qty' => 20, 'prep_minutes' => 6]);
        }

        $this->session = new CustomerSession;
        $this->session->forceFill([
            'canteen_id' => $this->canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();
        foreach (Menu::query()->withoutGlobalScope('tenant')->get() as $menu) {
            app(CartService::class)->add($this->session, $menu->id, 1);
        }

        $result = app(CheckoutService::class)->checkout($this->session, (string) Str::uuid());
        $this->order = $result->order;
        $this->token = (string) $result->trackingToken;

        $this->app->instance(ResolveTrackedOrder::class, new class($this->order->id) extends ResolveTrackedOrder
        {
            public function __construct(private int $id) {}

            public function current(Request $request): ?Order
            {
                return Order::query()->find($this->id);
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

    private function tenantOrder(string $tenantName): TenantOrder
    {
        return TenantOrder::query()->withoutGlobalScope('tenant')->where('order_id', $this->order->id)
            ->whereHas('tenant', fn ($q) => $q->where('display_name', $tenantName))->sole();
    }

    private function pay(): void
    {
        app(PaymentService::class)->confirmSandbox(app(PaymentService::class)->initiate($this->order));
    }

    public function test_tracker_shows_each_tenant_status_steps_and_estimate(): void
    {
        $tracker = Livewire::test('ordering::order-tracker', ['canteenSlug' => $this->canteen->slug])
            ->assertSee('Pesanan #'.$this->order->order_number)
            ->assertSee('MENUNGGU PEMBAYARAN');

        $this->pay();
        app(KitchenService::class)->advance($this->tenantOrder('Warung Bu Rina'), 'preparing');
        foreach (['preparing', 'ready'] as $status) {
            app(KitchenService::class)->advance($this->tenantOrder('Kopi Serambi'), $status);
        }

        $tracker->call('refresh')
            ->assertSee('REAL-TIME')->assertSee('POLLING 15 DTK')
            ->assertSeeInOrder(['Warung Bu Rina', 'DIMASAK', 'DITERIMA', 'DIMASAK', 'SIAP', 'SELESAI', 'estimasi'])
            ->assertSeeHtml('data-test="track-detail">estimasi ± ')
            ->assertSeeInOrder(['Kopi Serambi', 'SIAP DIAMBIL', 'silakan ambil di konter']);
    }

    public function test_status_change_is_broadcast_on_private_order_channel_without_internal_ids(): void
    {
        $this->pay();
        Event::fake([OrderTrackingUpdated::class]);

        app(KitchenService::class)->advance($this->tenantOrder('Warung Bu Rina'), 'preparing');

        Event::assertDispatched(OrderTrackingUpdated::class, function (OrderTrackingUpdated $event): bool {
            return $event->broadcastOn()[0]->name === 'private-order.'.$this->order->public_id
                && $event->broadcastWith() === ['tenant' => 'Warung Bu Rina', 'status' => 'preparing'];
        });

        Livewire::test('ordering::order-tracker', ['canteenSlug' => $this->canteen->slug])
            ->assertSeeHtml('setInterval')->assertSeeHtml('15000');
        $this->assertArrayHasKey('echo-private:order.'.$this->order->public_id.',.OrderTrackingUpdated',
            Livewire::test('ordering::order-tracker', ['canteenSlug' => $this->canteen->slug])->instance()->getListeners());
    }

    public function test_channel_authorization_requires_owner_session_and_valid_tracking_token(): void
    {
        $owner = new CustomerChannelUser((string) $this->session->id);
        $stranger = new CustomerChannelUser((string) Str::ulid());

        $this->assertTrue(OrderChannels::canTrack($owner, (string) $this->order->public_id, $this->token));
        $this->assertFalse(OrderChannels::canTrack($owner, (string) $this->order->public_id, 'token-palsu'));
        $this->assertFalse(OrderChannels::canTrack($owner, (string) $this->order->public_id, null));
        $this->assertFalse(OrderChannels::canTrack($stranger, (string) $this->order->public_id, $this->token));
        $this->assertGreaterThanOrEqual(43, strlen($this->token), 'token ≥ 256 bit (base64url)');
    }

    public function test_customer_broadcast_auth_endpoint_rejects_requests_without_customer_session(): void
    {
        $this->post(route('customer.broadcast-auth'), ['channel_name' => 'private-order.'.$this->order->public_id, 'socket_id' => '1.1'])
            ->assertForbidden();
    }
}
