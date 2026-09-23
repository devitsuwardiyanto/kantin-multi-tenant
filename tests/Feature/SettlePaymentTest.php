<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantBalance;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\SettlePayment;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettlePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    /** @return array{0: Tenant, 1: Payment} */
    private function paidPayment(int $price = 20000, int $qty = 2, float $commission = 0.15): array
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => $commission, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => $price, 'stock_qty' => 50]);

        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();

        app(CartService::class)->add($session, $menu->id, $qty);
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;
        $payment = app(PaymentService::class)->initiate($order);
        app(PaymentService::class)->confirmSandbox($payment); // menandai lunas + settlement

        return [$tenant, $payment->fresh()];
    }

    public function test_sandbox_confirmation_settles_to_available_balance(): void
    {
        // subtotal 40000, komisi 6000, net 34000
        [$tenant] = $this->paidPayment(20000, 2, 0.15);

        $this->assertSame(34000, (int) TenantBalance::query()->find($tenant->id)->available_amount);
        $this->assertSame(1, DB::table('ledger_entries')->where('tenant_id', $tenant->id)->where('type', 'sale_credit')->count());
        $this->assertSame(1, DB::table('ledger_entries')->where('tenant_id', $tenant->id)->where('type', 'commission_debit')->count());
    }

    public function test_settlement_is_idempotent(): void
    {
        [$tenant, $payment] = $this->paidPayment(20000, 2, 0.15);

        // Panggil settle lagi secara langsung: tak menggandakan saldo/ledger.
        app(SettlePayment::class)->settle($payment);

        $this->assertSame(34000, (int) TenantBalance::query()->find($tenant->id)->available_amount);
        $this->assertSame(1, DB::table('ledger_entries')->where('tenant_id', $tenant->id)->where('type', 'sale_credit')->count());
    }

    public function test_reversal_debits_balance_and_marks_refunded(): void
    {
        [$tenant, $payment] = $this->paidPayment(20000, 2, 0.15);
        $this->assertSame(34000, (int) TenantBalance::query()->find($tenant->id)->available_amount);

        app(SettlePayment::class)->reverse($payment);

        $this->assertSame(0, (int) TenantBalance::query()->find($tenant->id)->available_amount);
        $this->assertSame(-34000, (int) DB::table('ledger_entries')->where('tenant_id', $tenant->id)->where('type', 'reversal')->value('available_delta'));
        $this->assertSame('refunded', $payment->fresh()->status);
    }
}
