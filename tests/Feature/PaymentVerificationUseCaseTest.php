<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Ordering\Services\ResolveTrackedOrder;
use App\Modules\Payments\Events\PaymentVerified;
use App\Modules\Payments\Services\PaymentService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UC-08 Verifikasi Pembayaran: signature (2a → 401 + audit), payment_event append-only dengan
 * dedup (1a), nominal dicocokkan (3a → tinjau manual), dibayar tepat satu kali dengan baris
 * terkunci lalu settlement (UC-10), event setelah commit untuk KDS/notifikasi, expire/deny →
 * pesanan dibatalkan dan stok kembali (4a), serta hasil verifikasi di layar pelanggan.
 */
class PaymentVerificationUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'uc08-secret';

    private Canteen $canteen;

    private Menu $menu;

    private Order $order;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.qris.webhook_secret' => self::SECRET]);

        $this->canteen = Canteen::factory()->create(['tax_rate' => 0.1000, 'service_fee_rate' => 0.0200]);
        $tenant = Tenant::factory()->create(['canteen_id' => $this->canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.10, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        $this->menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => 18000, 'stock_qty' => 10]);

        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $this->canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();
        app(CartService::class)->add($session, $this->menu->id, 2);

        $this->order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;
        $this->payment = app(PaymentService::class)->initiate($this->order);
    }

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function webhook(string $eventId, array $overrides = [], ?string $signature = null): TestResponse
    {
        $body = json_encode(array_merge([
            'event_id' => $eventId,
            'payment_reference' => $this->payment->payment_reference,
            'status' => 'settlement',
            'amount' => $this->payment->amount,
        ], $overrides), JSON_THROW_ON_ERROR);

        return $this->call('POST', '/webhooks/qris', [], [], [], [
            'HTTP_X_QRIS_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, self::SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], $body);
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

    public function test_settlement_callback_marks_paid_once_settles_and_dispatches_after_commit(): void
    {
        Event::fake([PaymentVerified::class]);

        $this->webhook('evt-1')->assertOk()->assertJson(['status' => 'ok']);
        $this->webhook('evt-1')->assertOk()->assertJson(['status' => 'duplicate']);
        $this->webhook('evt-2')->assertOk()->assertJson(['status' => 'ok']);

        $this->assertSame('paid', $this->payment->fresh()->status);
        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertSame(2, DB::table('payment_events')->where('payment_id', $this->payment->id)->where('result', 'verified')->count());
        $this->assertSame(1, DB::table('ledger_entries')->where('type', 'sale_credit')->count(), 'settlement tepat satu kali');
        Event::assertDispatchedTimes(PaymentVerified::class, 1);

        $this->bindOrder();
        Livewire::test('payments::order-payment', ['canteenSlug' => $this->canteen->slug])
            ->assertSee('Pembayaran terverifikasi')
            ->assertSee($this->payment->payment_reference)
            ->assertSee('SETTLEMENT')
            ->assertSee('Lacak pesanan saya');
    }

    public function test_invalid_signature_returns_401_and_is_audited(): void
    {
        $this->webhook('evt-bad', [], 'deadbeef')->assertStatus(401);

        $this->assertSame(0, DB::table('payment_events')->count());
        $this->assertSame('pending', $this->payment->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'invalid_signature')->exists());
    }

    public function test_amount_mismatch_is_flagged_for_manual_review(): void
    {
        $this->webhook('evt-short', ['amount' => $this->payment->amount - 1000])
            ->assertOk()->assertJson(['status' => 'needs_review']);

        $this->assertSame('needs_review', $this->payment->fresh()->status);
        $this->assertSame('awaiting_payment', $this->order->fresh()->status);
        $this->assertSame('amount_mismatch', DB::table('payment_events')->value('result'));
        $this->assertSame(0, DB::table('ledger_entries')->count());
        $this->assertTrue(AuditLog::query()->where('action', 'amount_mismatch')->exists());

        $this->bindOrder();
        Livewire::test('payments::order-payment', ['canteenSlug' => $this->canteen->slug])
            ->assertSee('perlu ditinjau pengelola kantin');
    }

    public function test_callback_without_amount_is_rejected_as_bad_request(): void
    {
        $body = json_encode(['event_id' => 'evt-na', 'payment_reference' => $this->payment->payment_reference, 'status' => 'settlement'], JSON_THROW_ON_ERROR);

        $this->call('POST', '/webhooks/qris', [], [], [], [
            'HTTP_X_QRIS_SIGNATURE' => hash_hmac('sha256', $body, self::SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertStatus(400);

        $this->assertSame(0, DB::table('payment_events')->count());
    }

    public function test_expire_or_deny_cancels_order_and_restores_stock(): void
    {
        $this->assertSame(8, $this->menu->fresh()->stock_qty);

        $this->webhook('evt-exp', ['status' => 'expire', 'amount' => null])->assertOk()->assertJson(['status' => 'cancelled']);

        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertSame('failed', $this->payment->fresh()->status);
        $this->assertSame(10, $this->menu->fresh()->stock_qty);
        $this->assertSame('cancelled', DB::table('payment_events')->value('result'));
    }

    public function test_payment_arriving_after_customer_cancelled_needs_review_without_settlement(): void
    {
        app(PaymentService::class)->cancelUnpaid($this->order);

        $this->webhook('evt-late')->assertOk()->assertJson(['status' => 'needs_review']);

        $this->assertSame('needs_review', $this->payment->fresh()->status);
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertSame(0, DB::table('ledger_entries')->count());
        $this->assertTrue(AuditLog::query()->where('action', 'paid_after_cancel')->exists());
    }
}
