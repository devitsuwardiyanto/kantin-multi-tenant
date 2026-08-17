<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Tenant;
use App\Models\TenantBalance;
use App\Models\TenantBankAccount;
use App\Models\User;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Exceptions\WithdrawalException;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\WithdrawalService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class WithdrawalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    /** @return array{0: Tenant, 1: TenantBankAccount, 2: User} Saldo available = 34000 (subtotal 40000, komisi 15%). */
    private function fundedTenant(): array
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

        $account = (new TenantBankAccount)->forceFill([
            'tenant_id' => $tenant->id, 'bank_code' => 'BCA', 'account_holder' => 'Tenant',
            'account_last4' => '1234', 'account_number_cipher' => '1234567890', 'status' => 'verified', 'is_primary' => true,
        ]);
        $account->save();

        $user = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);

        return [$tenant, $account, $user];
    }

    public function test_request_holds_funds_and_records_ledger(): void
    {
        [$tenant, $account, $user] = $this->fundedTenant();

        $withdrawal = app(WithdrawalService::class)->request($account, 30000, $user);

        $this->assertSame('requested', $withdrawal->status);
        $this->assertSame($tenant->id, $withdrawal->active_tenant_lock);

        $balance = TenantBalance::query()->find($tenant->id);
        $this->assertSame(4000, (int) $balance->available_amount);  // 34000 - 30000
        $this->assertSame(30000, (int) $balance->held_amount);
        $this->assertSame(-30000, (int) DB::table('ledger_entries')->where('withdrawal_id', $withdrawal->id)->where('type', 'hold')->value('available_delta'));
        $this->assertSame(30000, (int) DB::table('ledger_entries')->where('withdrawal_id', $withdrawal->id)->where('type', 'hold')->value('held_delta'));
    }

    public function test_request_rejects_insufficient_unverified_and_invalid(): void
    {
        [, $account, $user] = $this->fundedTenant();

        try {
            app(WithdrawalService::class)->request($account, 999999, $user);
            $this->fail('melebihi saldo harus ditolak');
        } catch (WithdrawalException) {
        }

        $account->forceFill(['status' => 'unverified'])->save();
        $this->expectException(WithdrawalException::class);
        app(WithdrawalService::class)->request($account, 1000, $user);
    }

    public function test_only_one_active_withdrawal_per_tenant(): void
    {
        [, $account, $user] = $this->fundedTenant();
        app(WithdrawalService::class)->request($account, 10000, $user);

        $this->expectException(WithdrawalException::class);
        app(WithdrawalService::class)->request($account, 10000, $user);
    }

    public function test_approve_debits_held_and_frees_lock(): void
    {
        [$tenant, $account, $user] = $this->fundedTenant();
        $withdrawal = app(WithdrawalService::class)->request($account, 30000, $user);
        $reviewer = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);

        app(WithdrawalService::class)->approve($withdrawal, $reviewer);

        $withdrawal->refresh();
        $this->assertSame('paid', $withdrawal->status);
        $this->assertNull($withdrawal->active_tenant_lock);
        $balance = TenantBalance::query()->find($tenant->id);
        $this->assertSame(4000, (int) $balance->available_amount);
        $this->assertSame(0, (int) $balance->held_amount);
        $this->assertSame(-30000, (int) DB::table('ledger_entries')->where('withdrawal_id', $withdrawal->id)->where('type', 'withdrawal_debit')->value('held_delta'));

        // Lock bebas → boleh mengajukan lagi.
        $again = app(WithdrawalService::class)->request($account, 1000, $user);
        $this->assertSame('requested', $again->status);
    }

    public function test_reject_releases_held_back_to_available(): void
    {
        [$tenant, $account, $user] = $this->fundedTenant();
        $withdrawal = app(WithdrawalService::class)->request($account, 30000, $user);
        $reviewer = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);

        app(WithdrawalService::class)->reject($withdrawal, $reviewer);

        $withdrawal->refresh();
        $this->assertSame('rejected', $withdrawal->status);
        $this->assertNull($withdrawal->active_tenant_lock);
        $balance = TenantBalance::query()->find($tenant->id);
        $this->assertSame(34000, (int) $balance->available_amount); // dana kembali
        $this->assertSame(0, (int) $balance->held_amount);
    }
}
