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
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Services\PaymentService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WebhookSettlementTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

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

    /** @return array{0: Tenant, 1: Menu, 2: CustomerSession, 3: Canteen} */
    private function tenantMenu(Canteen $canteen, int $price, float $commission = 0.15): array
    {
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => $commission, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => $price, 'stock_qty' => 50]);

        return [$tenant, $menu, $this->newSession($canteen), $canteen];
    }

    private function newSession(Canteen $canteen): CustomerSession
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

    private function placeAndInitiate(Canteen $canteen, Menu $menu, CustomerSession $session, int $qty = 1): Payment
    {
        app(CartService::class)->add($session, $menu->id, $qty);
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;

        return app(PaymentService::class)->initiate($order);
    }

    private function postWebhook(string $reference, string $eventId, string $status = 'success', ?string $overrideSignature = null): TestResponse
    {
        $body = json_encode(['event_id' => $eventId, 'payment_reference' => $reference, 'status' => $status], JSON_THROW_ON_ERROR);
        $signature = $overrideSignature ?? hash_hmac('sha256', $body, self::SECRET);

        return $this->call('POST', '/webhooks/qris', [], [], [], [
            'HTTP_X_QRIS_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_valid_webhook_marks_paid_and_settles_split_ledger(): void
    {
        $canteen = Canteen::factory()->create();
        [$tenant, $menu, $session] = $this->tenantMenu($canteen, 20000, 0.15);
        $payment = $this->placeAndInitiate($canteen, $menu, $session, 2); // subtotal 40000, komisi 6000, net 34000

        $this->postWebhook($payment->payment_reference, 'evt-1')->assertOk()->assertJson(['status' => 'ok']);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('paid', Order::query()->find($payment->order_id)->status);

        // Ledger split: sale_credit + commission_debit
        $this->assertSame(40000, (int) DB::table('ledger_entries')->where('tenant_id', $tenant->id)->where('type', 'sale_credit')->value('available_delta'));
        $this->assertSame(-6000, (int) DB::table('ledger_entries')->where('tenant_id', $tenant->id)->where('type', 'commission_debit')->value('available_delta'));

        // Saldo tenant = net
        $this->assertSame(34000, (int) TenantBalance::query()->find($tenant->id)->available_amount);

        // Event verified
        $this->assertSame(1, DB::table('payment_events')->where('provider_event_id', 'evt-1')->where('result', 'verified')->count());
    }

    public function test_invalid_signature_is_rejected_without_side_effects(): void
    {
        $canteen = Canteen::factory()->create();
        [, $menu, $session] = $this->tenantMenu($canteen, 20000);
        $payment = $this->placeAndInitiate($canteen, $menu, $session);

        $this->postWebhook($payment->payment_reference, 'evt-x', 'success', overrideSignature: 'deadbeef')
            ->assertStatus(401);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(0, DB::table('payment_events')->count());
        $this->assertSame(0, DB::table('ledger_entries')->count());
    }

    public function test_duplicate_event_is_idempotent(): void
    {
        $canteen = Canteen::factory()->create();
        [$tenant, $menu, $session] = $this->tenantMenu($canteen, 25000, 0.20);
        $payment = $this->placeAndInitiate($canteen, $menu, $session, 1); // subtotal 25000, komisi 5000, net 20000

        $this->postWebhook($payment->payment_reference, 'evt-dup')->assertOk();
        $this->postWebhook($payment->payment_reference, 'evt-dup')->assertOk()->assertJson(['status' => 'duplicate']);

        // Tidak dobel: satu event, satu pasang ledger, saldo tetap net
        $this->assertSame(1, DB::table('payment_events')->where('provider_event_id', 'evt-dup')->count());
        $this->assertSame(1, DB::table('ledger_entries')->where('tenant_id', $tenant->id)->where('type', 'sale_credit')->count());
        $this->assertSame(20000, (int) TenantBalance::query()->find($tenant->id)->available_amount);
    }

    public function test_unknown_payment_reference_returns_404(): void
    {
        $this->postWebhook('PAY-TIDAKADA', 'evt-404')->assertNotFound();
    }

    public function test_webhook_splits_across_tenants(): void
    {
        $canteen = Canteen::factory()->create();
        [$tenantA, $menuA, $session] = $this->tenantMenu($canteen, 20000, 0.15); // net 17000
        $tenantB = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $tenantB->id, 'commission_rate' => 0.10, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        $menuB = Menu::factory()->create(['tenant_id' => $tenantB->id, 'base_price' => 30000, 'stock_qty' => 50]); // net 27000

        app(CartService::class)->add($session, $menuA->id, 1);
        app(CartService::class)->add($session, $menuB->id, 1);
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;
        $payment = app(PaymentService::class)->initiate($order);

        $this->postWebhook($payment->payment_reference, 'evt-split')->assertOk();

        $this->assertSame(17000, (int) TenantBalance::query()->find($tenantA->id)->available_amount);
        $this->assertSame(27000, (int) TenantBalance::query()->find($tenantB->id)->available_amount);
    }
}
