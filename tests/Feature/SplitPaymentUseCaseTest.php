<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantBalance;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\SettlePayment;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * UC-10 Split Payment Otomatis: kredit tenant = subtotal − komisi snapshot, kredit pengelola =
 * komisi + pajak + biaya layanan (+ selisih pembulatan, alur 1a), rincian pemecahan tersimpan,
 * Σ alokasi = nominal (selisih 0), idempoten, dan kegagalan di tengah → rollback + proses ulang (3a).
 */
class SplitPaymentUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'uc10-secret';

    private Canteen $canteen;

    private Tenant $warung;

    private Tenant $kopi;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.qris.webhook_secret' => self::SECRET]);

        $this->canteen = Canteen::factory()->create(['tax_rate' => 0.1000, 'service_fee_rate' => 0.0200]);
        $this->warung = $this->tenant('Warung Bu Rina');
        $this->kopi = $this->tenant('Kopi Serambi');
        $ayam = Menu::factory()->create(['tenant_id' => $this->warung->id, 'base_price' => 18000, 'stock_qty' => 10]);
        $esKopi = Menu::factory()->create(['tenant_id' => $this->kopi->id, 'base_price' => 18000, 'stock_qty' => 10]);

        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $this->canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();
        app(CartService::class)->add($session, $ayam->id, 1);
        app(CartService::class)->add($session, $esKopi->id, 2);

        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;
        $this->payment = app(PaymentService::class)->initiate($order);
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
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.10, 'valid_from' => now()->subMonth(), 'valid_to' => null]);

        return $tenant;
    }

    private function settleViaWebhook(string $eventId = 'evt-split'): void
    {
        $body = json_encode(['event_id' => $eventId, 'payment_reference' => $this->payment->payment_reference, 'status' => 'settlement', 'amount' => $this->payment->amount], JSON_THROW_ON_ERROR);
        $this->call('POST', '/webhooks/qris', [], [], [], [
            'HTTP_X_QRIS_SIGNATURE' => hash_hmac('sha256', $body, self::SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function balance(Tenant $tenant): int
    {
        return (int) TenantBalance::query()->find($tenant->id)?->available_amount;
    }

    public function test_split_credits_tenants_net_and_canteen_commission_tax_and_fee_with_zero_difference(): void
    {
        $this->settleViaWebhook();

        // 18.000 − 10% = 16.200; 36.000 − 10% = 32.400; pengelola = komisi 5.400 + pajak 5.400 + layanan 1.080.
        $this->assertSame(60480, $this->payment->amount);
        $this->assertSame(16200, $this->balance($this->warung));
        $this->assertSame(32400, $this->balance($this->kopi));

        $allocations = DB::table('payment_allocations')->where('payment_id', $this->payment->id)->pluck('amount', 'recipient_key');
        $this->assertSame(collect([
            'canteen' => 11880,
            'tenant:'.$this->kopi->id => 32400,
            'tenant:'.$this->warung->id => 16200,
        ])->sortKeys()->all(), collect($allocations)->map(fn ($v): int => (int) $v)->sortKeys()->all());
        $this->assertSame($this->payment->amount, (int) collect($allocations)->sum(), 'selisih 0 Rupiah');

        $platform = DB::table('platform_ledger_entries')->where('payment_id', $this->payment->id)->pluck('amount', 'type')->map(fn ($v): int => (int) $v)->all();
        $this->assertSame(['commission_credit' => 5400, 'service_fee_credit' => 1080, 'tax_credit' => 5400], collect($platform)->sortKeys()->all());
    }

    public function test_commission_uses_checkout_snapshot_not_current_master_rate(): void
    {
        CommissionScheme::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->warung->id)->update(['commission_rate' => 0.30]);

        $this->settleViaWebhook();

        $this->assertSame(16200, $this->balance($this->warung), 'tetap 10% sesuai snapshot tenant_order');
    }

    public function test_split_is_idempotent(): void
    {
        $this->settleViaWebhook('evt-a');
        app(SettlePayment::class)->settle($this->payment->fresh());
        $this->settleViaWebhook('evt-b');

        $this->assertSame(3, DB::table('payment_allocations')->where('payment_id', $this->payment->id)->count());
        $this->assertSame(3, DB::table('platform_ledger_entries')->where('payment_id', $this->payment->id)->count());
        $this->assertSame(16200, $this->balance($this->warung));
    }

    public function test_rounding_remainder_goes_to_canteen_and_is_logged(): void
    {
        $order = Order::query()->findOrFail($this->payment->order_id);
        // Nominal gateway dibulatkan ke atas 20 Rupiah (mis. kebijakan provider).
        $this->payment->forceFill(['amount' => 60500, 'status' => 'paid'])->save();

        app(SettlePayment::class)->settle($this->payment->fresh());

        $this->assertSame(20, (int) DB::table('platform_ledger_entries')->where('type', 'rounding_credit')->value('amount'));
        $this->assertSame(20, (int) DB::table('payment_allocations')->where('recipient_key', 'canteen')->value('rounding_amount'));
        $this->assertSame(60500, (int) DB::table('payment_allocations')->where('payment_id', $this->payment->id)->sum('amount'));
        $this->assertSame(60480, $order->grand_total_amount);
    }

    public function test_failure_midway_rolls_back_everything_and_can_be_reprocessed(): void
    {
        $this->app->instance(SettlePayment::class, new class(app(AuditLogger::class)) extends SettlePayment
        {
            public function settle(Payment $payment): void
            {
                parent::settle($payment);

                throw new RuntimeException('koneksi basis data terputus');
            }
        });

        $this->settleViaWebhook();

        $this->assertSame('settlement_failed', $this->payment->fresh()->status);
        $this->assertSame('awaiting_payment', Order::query()->find($this->payment->order_id)->status);
        $this->assertSame(0, DB::table('ledger_entries')->count());
        $this->assertSame(0, DB::table('payment_allocations')->count());
        $this->assertSame(0, DB::table('platform_ledger_entries')->count());
        $this->assertSame(0, $this->balance($this->warung));

        $this->app->forgetInstance(SettlePayment::class);
        $this->artisan('payments:reprocess-settlements')->expectsOutputToContain('1 pembayaran diproses ulang')->assertSuccessful();

        $this->assertSame('paid', $this->payment->fresh()->status);
        $this->assertSame(16200, $this->balance($this->warung));
        $this->assertSame($this->payment->amount, (int) DB::table('payment_allocations')->sum('amount'));
    }

    public function test_reversal_also_reverses_canteen_credits(): void
    {
        $this->settleViaWebhook();

        app(SettlePayment::class)->reverse($this->payment->fresh());

        $this->assertSame(0, (int) DB::table('platform_ledger_entries')->where('payment_id', $this->payment->id)->sum('amount'));
        $this->assertSame(0, $this->balance($this->warung));
    }
}
