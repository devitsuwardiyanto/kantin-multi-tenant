<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Tenant;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Ordering\Services\ResolveTrackedOrder;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentChargeRequest;
use App\Modules\Payments\Data\QrisCharge;
use App\Modules\Payments\Exceptions\GatewayUnavailableException;
use App\Modules\Payments\Gateways\FakeQrisGateway;
use App\Modules\Payments\Services\PaymentService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UC-07 Bayar via QRIS Dinamis: QRIS otomatis setelah checkout dengan rincian split, gambar QR +
 * penghitung mundur ≤ 15 menit, nominal terkunci di payload, 3 percobaan saat gateway tidak
 * merespons (alur 1a), QRIS kedaluwarsa → QRIS baru (alur 4a), status dipantau sampai dibayar,
 * serta pembatalan pesanan yang belum dibayar (tombol pada mockup).
 */
class QrisPaymentUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private Canteen $canteen;

    private Menu $ayam;

    private string $trackingToken;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->canteen = Canteen::factory()->create(['name' => 'Kantin Teknik', 'tax_rate' => 0.1000, 'service_fee_rate' => 0.0200]);
        $warung = $this->tenant('Warung Bu Rina');
        $kopi = $this->tenant('Kopi Serambi');
        $this->ayam = Menu::factory()->create(['tenant_id' => $warung->id, 'base_price' => 18000, 'stock_qty' => 10]);
        $esKopi = Menu::factory()->create(['tenant_id' => $kopi->id, 'base_price' => 18000, 'stock_qty' => 10]);

        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $this->canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();
        app(CartService::class)->add($session, $this->ayam->id, 2);
        app(CartService::class)->add($session, $esKopi->id, 1);

        $result = app(CheckoutService::class)->checkout($session, (string) Str::uuid());
        $this->trackingToken = (string) $result->trackingToken;
        $this->order = $result->order;
    }

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    private function tenant(string $name): Tenant
    {
        $tenant = Tenant::factory()->create(['canteen_id' => $this->canteen->id, 'display_name' => $name]);
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.15, 'valid_from' => now()->subMonth(), 'valid_to' => null]);

        return $tenant;
    }

    private function bindOrder(): void
    {
        $this->app->instance(ResolveTrackedOrder::class, new class($this->order->id) extends ResolveTrackedOrder
        {
            public function __construct(private int $id) {}

            public function current(Request $request): ?Order
            {
                return Order::query()->find($this->id);
            }
        });
    }

    /**
     * Gateway pembungkus yang mencatat setiap permintaan (dan dapat dibuat tidak merespons).
     */
    private function spyGateway(bool $unavailable = false): SpyQrisGateway
    {
        $spy = new SpyQrisGateway($unavailable);
        $this->app->instance(PaymentGateway::class, $spy);

        return $spy;
    }

    public function test_checkout_lands_on_payment_screen_with_qris_image(): void
    {
        $this->withUnencryptedCookie('order_tracking', $this->trackingToken)
            ->get(route('customer.order.show', ['canteen' => $this->canteen->slug]))
            ->assertOk()
            ->assertSee('Pembayaran QRIS')
            ->assertSee('#'.$this->order->order_number)
            ->assertSee('Rp'.number_format($this->order->grand_total_amount, 0, ',', '.'))
            ->assertSee('Menunggu pembayaran')
            ->assertSee('NMID •••8821')
            ->assertSee('<svg', false);

        $payment = Payment::query()->where('order_id', $this->order->id)->sole();
        $this->assertStringStartsWith('PAY-', $payment->payment_reference);
    }

    public function test_charge_request_carries_splits_and_locked_amount_with_max_15_minute_expiry(): void
    {
        $spy = $this->spyGateway();

        $payment = app(PaymentService::class)->initiate($this->order);

        $request = $spy->requests[0];
        $this->assertCount(2, $request->splits);
        $this->assertSame($this->order->grand_total_amount, array_sum(array_column($request->splits, 'gross')));
        $this->assertSame($this->order->grand_total_amount, $request->amount);
        $this->assertLessThanOrEqual(900, $request->expiresInSeconds);

        $attempt = $payment->latestAttempt;
        $amount = (string) $this->order->grand_total_amount;
        $this->assertStringContainsString('54'.str_pad((string) strlen($amount), 2, '0', STR_PAD_LEFT).$amount, (string) $attempt->qris_payload);
        $this->assertTrue($attempt->expires_at->lessThanOrEqualTo(now()->addMinutes(15)));
    }

    public function test_gateway_unavailable_is_retried_three_times_then_shows_disruption_message(): void
    {
        Sleep::fake();
        $spy = $this->spyGateway(unavailable: true);
        $this->bindOrder();

        Livewire::test('payments::order-payment', ['canteenSlug' => $this->canteen->slug])
            ->assertSee('Pembayaran sedang mengalami gangguan. Silakan coba beberapa saat lagi.')
            ->assertSee('Coba lagi');

        $this->assertCount(3, $spy->requests);
        $this->assertSame(0, PaymentAttempt::query()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'gateway_unavailable')->exists());
        $this->assertSame('awaiting_payment', $this->order->fresh()->status);
    }

    public function test_expired_qris_is_cancelled_and_a_new_qris_can_be_generated(): void
    {
        $this->bindOrder();
        Livewire::test('payments::order-payment', ['canteenSlug' => $this->canteen->slug]);
        $first = PaymentAttempt::query()->sole();

        $this->travel(16)->minutes();

        Livewire::test('payments::order-payment', ['canteenSlug' => $this->canteen->slug])
            ->assertSee('QRIS kedaluwarsa sebelum dibayar.')
            ->assertSee('Buat QRIS baru')
            ->call('regenerate')
            ->assertSee('Menunggu pembayaran');

        $this->assertSame('expired', $first->fresh()->status);
        $this->assertSame(2, PaymentAttempt::query()->count());
        $this->assertSame('pending', PaymentAttempt::query()->latest('id')->first()->status);
        $this->assertSame('awaiting_payment', $this->order->fresh()->status);
    }

    public function test_status_is_polled_until_payment_is_verified(): void
    {
        $this->bindOrder();
        $component = Livewire::test('payments::order-payment', ['canteenSlug' => $this->canteen->slug])
            ->assertSeeHtml('wire:poll.3s');

        // Verifikasi pembayaran (UC-08) terjadi di luar komponen; poll berikutnya melihatnya.
        app(PaymentService::class)->confirmSandbox(Payment::query()->sole());

        $component->call('$refresh')
            ->assertSee('Pembayaran berhasil')
            ->assertDontSeeHtml('wire:poll.3s');
    }

    public function test_customer_cancels_unpaid_order_and_stock_is_restored(): void
    {
        $this->bindOrder();
        $this->assertSame(8, $this->ayam->fresh()->stock_qty);

        Livewire::test('payments::order-payment', ['canteenSlug' => $this->canteen->slug])
            ->call('cancel')
            ->assertSee('Pesanan dibatalkan.');

        $order = $this->order->fresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame(['cancelled', 'cancelled'], $order->tenantOrders()->pluck('status')->all());
        $this->assertSame(10, $this->ayam->fresh()->stock_qty);
        $this->assertSame('failed', Payment::query()->sole()->status);
        $this->assertSame('expired', PaymentAttempt::query()->sole()->status);
    }

    public function test_paid_order_cannot_be_cancelled(): void
    {
        $this->bindOrder();
        $payment = app(PaymentService::class)->initiate($this->order);
        app(PaymentService::class)->confirmSandbox($payment);

        Livewire::test('payments::order-payment', ['canteenSlug' => $this->canteen->slug])
            ->call('cancel')
            ->assertSee('Pembayaran berhasil');

        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertSame(8, $this->ayam->fresh()->stock_qty);
    }
}

/**
 * Gateway uji: mencatat setiap permintaan charge dan dapat menirukan provider yang tidak merespons.
 */
final class SpyQrisGateway implements PaymentGateway
{
    /** @var list<PaymentChargeRequest> */
    public array $requests = [];

    public function __construct(private bool $unavailable = false) {}

    public function createQrisCharge(PaymentChargeRequest $request): QrisCharge
    {
        $this->requests[] = $request;
        if ($this->unavailable) {
            throw new GatewayUnavailableException('timeout');
        }

        return (new FakeQrisGateway)->createQrisCharge($request);
    }

    public function name(): string
    {
        return 'spy';
    }

    public function isSandbox(): bool
    {
        return true;
    }
}
