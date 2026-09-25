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
use App\Models\TenantBalance;
use App\Models\TenantOrder;
use App\Models\User;
use App\Models\UserCanteenRole;
use App\Modules\Kitchen\Services\KitchenService;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Exceptions\FollowUpException;
use App\Modules\Payments\Services\FollowUpService;
use App\Modules\Payments\Services\PaymentService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Temuan audit Pertemuan 14: tindak lanjut pengelola kantin atas kasus yang ditandai sistem —
 * UC-08 alur 3a (pembayaran perlu ditinjau) dan UC-15 alur 4a (pengembalian dana dari dapur).
 */
class FollowUpUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'follow-up-secret';

    private Canteen $canteen;

    private Tenant $tenant;

    private Menu $menu;

    private Order $order;

    private Payment $payment;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.qris.webhook_secret' => self::SECRET]);

        $this->canteen = Canteen::factory()->create(['tax_rate' => 0.1000, 'service_fee_rate' => 0.0200]);
        $this->tenant = Tenant::factory()->create(['canteen_id' => $this->canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $this->tenant->id, 'commission_rate' => 0.10, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        $this->menu = Menu::factory()->create(['tenant_id' => $this->tenant->id, 'base_price' => 20000, 'stock_qty' => 10]);

        $session = new CustomerSession;
        $session->forceFill(['canteen_id' => $this->canteen->id, 'session_token_hash' => OpaqueToken::issue(32)['hash'], 'status' => 'active', 'expires_at' => now()->addHours(4)])->save();
        app(CartService::class)->add($session, $this->menu->id, 2); // subtotal 40.000, pajak 4.000, layanan 800

        $this->order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;
        $this->payment = app(PaymentService::class)->initiate($this->order);

        $this->manager = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        UserCanteenRole::create(['user_id' => $this->manager->id, 'canteen_id' => $this->canteen->id, 'role' => 'manager']);
    }

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    private function webhook(string $eventId, int $amount): TestResponse
    {
        $body = json_encode(['event_id' => $eventId, 'payment_reference' => $this->payment->payment_reference, 'status' => 'settlement', 'amount' => $amount], JSON_THROW_ON_ERROR);

        return $this->call('POST', '/webhooks/qris', [], [], [], [
            'HTTP_X_QRIS_SIGNATURE' => hash_hmac('sha256', $body, self::SECRET), 'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function available(): int
    {
        return (int) TenantBalance::query()->find($this->tenant->id)?->available_amount;
    }

    public function test_mismatched_payment_is_listed_and_can_be_accepted_once(): void
    {
        $this->webhook('evt-short', $this->payment->amount - 1000)->assertOk();
        $this->assertSame('needs_review', $this->payment->fresh()->status);

        $component = Livewire::actingAs($this->manager)->test('payments::follow-ups')
            ->assertSee('#'.$this->order->order_number)
            ->assertSee('Nominal tidak cocok')
            ->set('notes.payment-'.$this->payment->id, 'Selisih Rp1.000 dibayar tunai di kasir.')
            ->call('accept', $this->payment->id)
            ->assertHasNoErrors()
            ->assertSee('Pembayaran diterima');

        $payment = $this->payment->fresh();
        $this->assertSame(['paid', 'accepted', $this->manager->id], [$payment->status, $payment->review_outcome, $payment->reviewed_by]);
        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertSame((int) $payment->amount, (int) DB::table('payment_allocations')->where('payment_id', $payment->id)->sum('amount'));
        $this->assertSame(36000, $this->available()); // 40.000 − komisi 10%

        app(FollowUpService::class)->acceptPayment($payment, $this->canteen->id, $this->manager, 'klik ganda');
        $this->assertSame(36000, $this->available(), 'persetujuan kedua tidak menggandakan settlement');
        $this->assertTrue(AuditLog::query()->where(['entity' => 'payment', 'action' => 'review_accepted'])->exists());
        $component->assertDontSee('Nominal tidak cocok');
    }

    public function test_note_is_required_and_refund_cancels_order_with_stock_returned(): void
    {
        $this->webhook('evt-short', $this->payment->amount - 1000);
        $this->assertSame(8, $this->menu->fresh()->stock_qty);

        Livewire::actingAs($this->manager)->test('payments::follow-ups')
            ->call('refundPayment', $this->payment->id)
            ->assertSee('Catatan tindak lanjut wajib diisi.')
            ->set('notes.payment-'.$this->payment->id, 'Dana ditransfer balik ke pelanggan.')
            ->call('refundPayment', $this->payment->id)
            ->assertHasNoErrors();

        $this->assertSame(['refunded', 'refunded'], [$this->payment->fresh()->status, $this->payment->fresh()->review_outcome]);
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertSame(10, $this->menu->fresh()->stock_qty);
    }

    public function test_kitchen_cancellation_refund_reverses_tenant_income_and_canteen_credits(): void
    {
        $this->webhook('evt-ok', $this->payment->amount)->assertOk();
        $this->assertSame(36000, $this->available());
        $tenantOrder = TenantOrder::query()->withoutGlobalScope('tenant')->where('order_id', $this->order->id)->firstOrFail();
        app(KitchenService::class)->advance($tenantOrder, 'cancelled', 'stok_habis');

        Livewire::actingAs($this->manager)->test('payments::follow-ups')
            ->assertSee('Bahan/stok habis')
            ->assertSee('Rp44.800')
            ->set('notes.refund-'.$tenantOrder->id, 'Dikembalikan tunai di kasir.')
            ->call('refundTenantOrder', $tenantOrder->id)
            ->assertHasNoErrors()
            ->assertSee('Dikembalikan tunai di kasir.');

        $tenantOrder = $tenantOrder->fresh();
        $this->assertSame(['refunded', 44800], [$tenantOrder->refund_status, $tenantOrder->refund_amount]);
        $this->assertSame(0, $this->available());
        $this->assertSame(-36000, (int) DB::table('ledger_entries')->where('tenant_id', $this->tenant->id)->where('type', 'reversal')->sum('available_delta'));
        $this->assertSame(0, (int) DB::table('platform_ledger_entries')->where('payment_id', $this->payment->id)->where('type', '!=', 'rounding_credit')->sum('amount'),
            'kredit komisi, pajak, dan biaya layanan sub-pesanan dibalik');

        app(FollowUpService::class)->refundTenantOrder($tenantOrder, $this->canteen->id, $this->manager, 'ulang');
        $this->assertSame(0, $this->available(), 'idempoten');
    }

    public function test_refund_is_blocked_when_tenant_income_was_already_withdrawn(): void
    {
        $this->webhook('evt-ok', $this->payment->amount);
        $tenantOrder = TenantOrder::query()->withoutGlobalScope('tenant')->where('order_id', $this->order->id)->firstOrFail();
        app(KitchenService::class)->advance($tenantOrder, 'cancelled', 'dapur_tutup');
        DB::table('tenant_balances')->where('tenant_id', $this->tenant->id)->update(['available_amount' => 1000]);

        $this->expectException(FollowUpException::class);
        $this->expectExceptionMessage('Saldo tenant tidak mencukupi');
        try {
            app(FollowUpService::class)->refundTenantOrder($tenantOrder, $this->canteen->id, $this->manager, 'coba');
        } finally {
            $this->assertSame('pending', $tenantOrder->fresh()->refund_status);
            $this->assertSame(0, DB::table('ledger_entries')->where('type', 'reversal')->count());
        }
    }

    public function test_other_canteen_manager_cannot_act_and_non_manager_is_forbidden(): void
    {
        $this->webhook('evt-short', $this->payment->amount - 1000);
        $otherManager = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        UserCanteenRole::create(['user_id' => $otherManager->id, 'canteen_id' => Canteen::factory()->create()->id, 'role' => 'manager']);

        Livewire::actingAs($otherManager)->test('payments::follow-ups')
            ->assertDontSee('#'.$this->order->order_number)
            ->set('notes.payment-'.$this->payment->id, 'coba')
            ->call('accept', $this->payment->id)
            ->assertSee('Data tidak ditemukan di kantin Anda.');
        $this->assertSame('needs_review', $this->payment->fresh()->status);

        $plainAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        Livewire::actingAs($plainAdmin)->test('payments::follow-ups')->assertForbidden();
    }

    public function test_page_is_linked_from_admin_dashboard(): void
    {
        $this->actingAs($this->manager)->get(route('admin.dashboard'))
            ->assertOk()->assertSee(route('admin.follow-ups'), false)->assertSee('Perlu tindak lanjut');
        $this->get(route('admin.follow-ups'))->assertOk()->assertSeeLivewire('payments::follow-ups');
    }
}
