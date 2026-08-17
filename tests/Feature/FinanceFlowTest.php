<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Tenant;
use App\Models\TenantBankAccount;
use App\Models\User;
use App\Models\UserCanteenRole;
use App\Models\UserTenantRole;
use App\Models\Withdrawal;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\WithdrawalService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    /** @return array{0: Canteen, 1: Tenant, 2: TenantBankAccount, 3: User} */
    private function scenario(): array
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

        $account = (new TenantBankAccount)->forceFill(['tenant_id' => $tenant->id, 'bank_code' => 'BCA', 'account_holder' => 'T', 'account_last4' => '1234', 'account_number_cipher' => '123', 'status' => 'verified', 'is_primary' => true]);
        $account->save();

        $member = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $member->id, 'tenant_id' => $tenant->id, 'role' => 'operator']);

        return [$canteen, $tenant, $account, $member];
    }

    public function test_finance_panel_forbidden_for_non_member(): void
    {
        [, $tenant] = $this->scenario();
        $outsider = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);

        Livewire::actingAs($outsider)
            ->test('tenant.finance-panel', ['tenantId' => $tenant->id])
            ->assertForbidden();
    }

    public function test_member_requests_withdrawal_via_panel(): void
    {
        [, $tenant, $account, $member] = $this->scenario();

        Livewire::actingAs($member)
            ->test('tenant.finance-panel', ['tenantId' => $tenant->id])
            ->set('amount', 10000)
            ->set('bankAccountId', $account->id)
            ->call('requestWithdrawal')
            ->assertHasNoErrors();

        $this->assertSame(1, Withdrawal::query()->where('tenant_id', $tenant->id)->where('status', 'requested')->count());
    }

    public function test_admin_reviews_and_cross_canteen_forbidden(): void
    {
        [$canteen, $tenant, $account, $member] = $this->scenario();
        $withdrawal = app(WithdrawalService::class)->request($account, 10000, $member);

        // Manager kantin A menyetujui.
        $manager = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        UserCanteenRole::create(['user_id' => $manager->id, 'canteen_id' => $canteen->id, 'role' => 'manager']);

        Livewire::actingAs($manager)
            ->test('admin.withdrawal-review')
            ->call('approve', $withdrawal->id)
            ->assertHasNoErrors();
        $this->assertSame('paid', $withdrawal->fresh()->status);

        // Manager kantin LAIN tak boleh meninjau penarikan kantin A.
        $otherCanteen = Canteen::factory()->create();
        $otherManager = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        UserCanteenRole::create(['user_id' => $otherManager->id, 'canteen_id' => $otherCanteen->id, 'role' => 'manager']);
        $second = app(WithdrawalService::class)->request($account, 5000, $member); // lock bebas setelah paid

        Livewire::actingAs($otherManager)
            ->test('admin.withdrawal-review')
            ->call('reject', $second->id)
            ->assertForbidden();
    }

    public function test_ledger_csv_export_downloads_for_member(): void
    {
        [, $tenant, , $member] = $this->scenario();

        $response = $this->actingAs($member)->get(route('tenant.finance.export', ['tenant' => $tenant->slug]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('sale_credit', $response->streamedContent());
    }
}
