<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Tenant;
use App\Models\TenantBankAccount;
use App\Models\User;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\WithdrawalService;
use App\Modules\Reporting\Services\TenantLedgerReport;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantLedgerReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    private function settledTenant(): Tenant
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.15, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => 20000, 'stock_qty' => 50]);

        $session = new CustomerSession;
        $session->forceFill(['canteen_id' => $canteen->id, 'session_token_hash' => OpaqueToken::issue(32)['hash'], 'status' => 'active', 'expires_at' => now()->addHours(4)])->save();
        app(CartService::class)->add($session, $menu->id, 2);
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;
        app(PaymentService::class)->confirmSandbox(app(PaymentService::class)->initiate($order));

        return $tenant;
    }

    public function test_summary_derives_gross_commission_net_from_ledger(): void
    {
        $tenant = $this->settledTenant();
        $summary = app(TenantLedgerReport::class)->summary($tenant->id);

        $this->assertSame(40000, $summary['gross_sales']);
        $this->assertSame(6000, $summary['commission']);
        $this->assertSame(34000, $summary['net']);
    }

    public function test_reconcile_matches_ledger_and_materialized_balance(): void
    {
        $tenant = $this->settledTenant();

        $recon = app(TenantLedgerReport::class)->reconcile($tenant->id);
        $this->assertTrue($recon['matches']);
        $this->assertSame(34000, $recon['ledger_available']);
        $this->assertSame(34000, $recon['balance_available']);

        // Setelah penarikan diajukan, rekonsiliasi tetap cocok (ledger = saldo).
        $account = (new TenantBankAccount)->forceFill(['tenant_id' => $tenant->id, 'bank_code' => 'BCA', 'account_holder' => 'T', 'account_last4' => '1234', 'account_number_cipher' => '123', 'status' => 'verified', 'is_primary' => true]);
        $account->save();
        $user = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        app(WithdrawalService::class)->request($account, 30000, $user);

        $recon2 = app(TenantLedgerReport::class)->reconcile($tenant->id);
        $this->assertTrue($recon2['matches']);
        $this->assertSame(4000, $recon2['balance_available']);
        $this->assertSame(30000, $recon2['balance_held']);
    }
}
