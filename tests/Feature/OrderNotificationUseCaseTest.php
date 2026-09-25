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
use App\Modules\Ordering\Contracts\NotificationGateway;
use App\Modules\Ordering\Data\CheckoutDetails;
use App\Modules\Ordering\Gateways\FakeWhatsAppGateway;
use App\Modules\Ordering\Jobs\SendCustomerNotification;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Ordering\Services\CustomerNotifier;
use App\Modules\Payments\Services\PaymentService;
use App\Support\Tokens\OpaqueToken;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * UC-12 Kirim Notifikasi Status: konfirmasi pembayaran (dari UC-08) dan perubahan status dapur
 * (dari UC-15) dikirim lewat antrean ke WhatsApp berisi nomor pesanan, tenant, dan status;
 * tercatat di notification_deliveries (idempoten); gagal → 3 percobaan dengan jeda bertingkat
 * lalu dicatat gagal tanpa mengganggu pesanan (4a/4b); tanpa nomor WhatsApp → tidak dikirim.
 */
class OrderNotificationUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private Canteen $canteen;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->canteen = Canteen::factory()->create(['name' => 'Kantin Teknik']);
        $this->tenant = Tenant::factory()->preOrder()->create(['canteen_id' => $this->canteen->id, 'display_name' => 'Kopi Serambi']);
        CommissionScheme::factory()->create(['tenant_id' => $this->tenant->id, 'commission_rate' => 0.10, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        Menu::factory()->create(['tenant_id' => $this->tenant->id, 'base_price' => 18000, 'stock_qty' => 20, 'prep_minutes' => 5]);
    }

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    private function paidOrder(?string $whatsapp = '6281234567890', ?CarbonInterface $scheduledAt = null): Order
    {
        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $this->canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();
        app(CartService::class)->add($session, Menu::query()->withoutGlobalScope('tenant')->value('id'), 2);
        $details = $whatsapp === null ? null : new CheckoutDetails($scheduledAt ? 'pickup' : 'dine_in', 'Dewi', $whatsapp);
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid(), $scheduledAt, $details)->order;
        app(PaymentService::class)->confirmSandbox(app(PaymentService::class)->initiate($order));

        return $order->fresh();
    }

    private function tenantOrder(Order $order): TenantOrder
    {
        return TenantOrder::query()->withoutGlobalScope('tenant')->where('order_id', $order->id)->sole();
    }

    /** @return array<string, mixed> */
    private function delivery(string $eventKey): array
    {
        $row = DB::table('notification_deliveries')->where('event_key', $eventKey)->first();

        return $row === null ? [] : ((array) $row) + ['payload_data' => json_decode((string) $row->payload, true)];
    }

    public function test_payment_confirmation_is_queued_and_sent_to_whatsapp(): void
    {
        $order = $this->paidOrder();

        $delivery = $this->delivery('order:'.$order->id.':paid');
        $this->assertSame('sent', $delivery['status']);
        $this->assertSame('whatsapp', $delivery['channel']);
        $this->assertSame(1, $delivery['attempts']);
        $this->assertStringStartsWith('Pembayaran Rp'.number_format($order->grand_total_amount, 0, ',', '.').' untuk pesanan #'.$order->order_number.' diterima. Pesanan diteruskan ke dapur. Lacak:', $delivery['payload_data']['message']);
        $this->assertStringStartsWith('FAKE-WA-', $delivery['payload_data']['provider_id']);
    }

    public function test_kitchen_status_changes_notify_customer_once_per_status(): void
    {
        $order = $this->paidOrder();
        $tenantOrder = $this->tenantOrder($order);

        app(KitchenService::class)->advance($tenantOrder, 'preparing');
        app(KitchenService::class)->advance($tenantOrder, 'ready');
        app(CustomerNotifier::class)->statusChanged($tenantOrder->fresh()); // kiriman ganda diabaikan

        $ready = $this->delivery('tenant_order:'.$tenantOrder->id.':ready');
        $this->assertSame('Halo Dewi! Pesanan #'.$order->order_number.' dari Kopi Serambi sudah SIAP DIAMBIL di konter. Tunjukkan nomor pesanan Anda, ya. ✅', $ready['payload_data']['message']);
        $this->assertSame(1, DB::table('notification_deliveries')->where('event_key', 'tenant_order:'.$tenantOrder->id.':ready')->count());
        $this->assertSame('sent', $this->delivery('tenant_order:'.$tenantOrder->id.':preparing')['status']);
    }

    public function test_no_whatsapp_number_means_no_notification(): void
    {
        $order = $this->paidOrder(whatsapp: null);

        app(KitchenService::class)->advance($this->tenantOrder($order), 'preparing');

        $this->assertSame(0, DB::table('notification_deliveries')->count());
    }

    public function test_failed_delivery_is_retried_three_times_with_backoff_then_logged_without_breaking_the_order(): void
    {
        $this->app->instance(NotificationGateway::class, new FakeWhatsAppGateway(unavailable: true));

        $order = $this->paidOrder();
        $this->assertSame('paid', $order->status, 'kegagalan notifikasi tidak menggagalkan pesanan');

        $id = (int) DB::table('notification_deliveries')->where('event_key', 'order:'.$order->id.':paid')->value('id');
        $this->assertSame('pending', $this->delivery('order:'.$order->id.':paid')['status']);

        $second = (new SendCustomerNotification($id))->withFakeQueueInteractions();
        $second->handle(app(NotificationGateway::class));
        $second->assertReleased(delay: 25);

        $third = (new SendCustomerNotification($id))->withFakeQueueInteractions();
        $third->handle(app(NotificationGateway::class));
        $third->assertNotReleased();

        $delivery = $this->delivery('order:'.$order->id.':paid');
        $this->assertSame('failed', $delivery['status']);
        $this->assertSame(3, $delivery['attempts']);
    }

    public function test_first_failed_attempt_is_released_after_five_seconds(): void
    {
        $order = $this->paidOrder(whatsapp: null);
        DB::table('notification_deliveries')->insert([
            'order_id' => $order->id, 'event_key' => 'manual', 'channel' => 'whatsapp', 'status' => 'pending', 'attempts' => 0,
            'payload' => json_encode(['to' => '6281', 'message' => 'x']), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $job = (new SendCustomerNotification((int) DB::table('notification_deliveries')->value('id')))->withFakeQueueInteractions();
        $job->handle(new FakeWhatsAppGateway(unavailable: true));

        $job->assertReleased(delay: 5);
    }

    public function test_pre_order_release_sends_pickup_reminder(): void
    {
        $order = $this->paidOrder(scheduledAt: now()->addDay()->setTime(12, 0));
        $tenantOrder = $this->tenantOrder($order);

        $this->travelTo($tenantOrder->release_at);
        $this->artisan('ordering:release-scheduled')->assertSuccessful();

        $this->assertStringStartsWith('Pengingat: pesanan pre-order #'.$order->order_number.' dari Kopi Serambi mulai dimasak dan siap sekitar pukul', $this->delivery('tenant_order:'.$tenantOrder->id.':reminder')['payload_data']['message']);
    }
}
