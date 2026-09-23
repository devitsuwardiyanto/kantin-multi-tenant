<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantBalance;
use App\Models\TenantBankAccount;
use App\Models\TenantOrder;
use App\Models\User;
use App\Models\UserTenantRole;
use App\Modules\Admin\Services\QrTokenService;
use App\Modules\Kitchen\Services\KitchenService;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\WithdrawalService;
use App\Modules\Reporting\Services\TenantLedgerReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Uji end-to-end capstone: seluruh perjalanan lintas modul dalam satu skenario —
 * scan QR → katalog/keranjang → checkout → pembayaran (webhook) → settlement →
 * dapur (status) → rekonsiliasi → penarikan dana. Membuktikan invarian menyeluruh.
 */
class EndToEndJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'e2e-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.qris.webhook_secret' => self::SECRET]);
    }

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    public function test_full_customer_to_withdrawal_journey(): void
    {
        // 1) Setup kantin, tenant, komisi, menu, meja, anggota tenant, pengelola.
        $canteen = Canteen::factory()->create(['tax_rate' => 0.1000, 'service_fee_rate' => 0.0200]);
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.15, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => 20000, 'stock_qty' => 10]);
        $table = DiningTable::factory()->create(['canteen_id' => $canteen->id, 'status' => 'active']);
        $member = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $member->id, 'tenant_id' => $tenant->id, 'role' => 'operator']);
        $reviewer = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);

        // 2) Scan QR → sesi anonim + cookie aman.
        $plain = app(QrTokenService::class)->issue($table);
        $this->get(route('customer.scan', ['token' => $plain]))
            ->assertRedirect(route('customer.home', ['canteen' => $canteen->slug]))
            ->assertCookie('customer_session');
        $session = CustomerSession::query()->firstOrFail();
        $this->assertSame($canteen->id, $session->canteen_id);

        // 3) Keranjang.
        app(CartService::class)->add($session, $menu->id, 2);

        // 4) Checkout atomik → order menunggu pembayaran; stok berkurang.
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertSame(8, $menu->fresh()->stock_qty);

        // 5) Pembayaran: inisiasi + webhook ber-signature → lunas + settlement.
        $payment = app(PaymentService::class)->initiate($order);
        $body = json_encode(['event_id' => 'e2e-1', 'payment_reference' => $payment->payment_reference, 'status' => 'success'], JSON_THROW_ON_ERROR);
        $this->call('POST', '/webhooks/qris', [], [], [], [
            'HTTP_X_QRIS_SIGNATURE' => hash_hmac('sha256', $body, self::SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(34000, (int) TenantBalance::query()->find($tenant->id)->available_amount);

        // 6) Dapur: seluruh transisi status.
        $tenantOrder = TenantOrder::query()->where('order_id', $order->id)->firstOrFail();
        foreach (['accepted', 'preparing', 'ready', 'completed'] as $target) {
            app(KitchenService::class)->advance($tenantOrder, $target);
        }
        $this->assertSame('completed', $tenantOrder->fresh()->status);

        // 7) Rekonsiliasi: saldo == akumulasi ledger.
        $this->assertTrue(app(TenantLedgerReport::class)->reconcile($tenant->id)['matches']);

        // 8) Penarikan: request (tahan) → approve (cairkan).
        $account = (new TenantBankAccount)->forceFill(['tenant_id' => $tenant->id, 'bank_code' => 'BCA', 'account_holder' => 'T', 'account_last4' => '1234', 'account_number_cipher' => '123', 'status' => 'verified', 'is_primary' => true]);
        $account->save();
        $withdrawal = app(WithdrawalService::class)->request($account, 30000, $member);
        $this->assertSame(30000, (int) TenantBalance::query()->find($tenant->id)->held_amount);
        app(WithdrawalService::class)->approve($withdrawal, $reviewer);

        // 9) Invarian akhir.
        $balance = TenantBalance::query()->find($tenant->id);
        $this->assertSame('paid', $withdrawal->fresh()->status);
        $this->assertSame(4000, (int) $balance->available_amount);
        $this->assertSame(0, (int) $balance->held_amount);
        $this->assertTrue(app(TenantLedgerReport::class)->reconcile($tenant->id)['matches'], 'rekonsiliasi tetap cocok di akhir perjalanan');
    }
}
