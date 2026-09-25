<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantBankAccount;
use App\Models\User;
use App\Models\UserTenantRole;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\WithdrawalService;
use App\Modules\Reporting\Services\TenantReconciliation;
use App\Support\Tokens\OpaqueToken;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UC-18 Lihat Rekonsiliasi Bagi Hasil: kotor, komisi, biaya lain, bersih per periode dari ledger;
 * entri per transaksi + referensi pembayaran; telusur ke pesanan asal (hanya item tenant ini);
 * pembayaran perlu peninjauan ditandai dan tidak dihitung ke saldo; saldo = Σ ledger.
 */
class ReconciliationUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private Canteen $canteen;

    private Tenant $tenant;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.withdrawal.minimum' => 1000]);
        $this->canteen = Canteen::factory()->create();
        $this->tenant = Tenant::factory()->create(['canteen_id' => $this->canteen->id, 'display_name' => 'Warung Bu Rina']);
        CommissionScheme::factory()->create(['tenant_id' => $this->tenant->id, 'commission_rate' => 0.10, 'valid_from' => now()->subYear(), 'valid_to' => null]);
        $this->member = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $this->member->id, 'tenant_id' => $this->tenant->id, 'role' => 'owner']);
    }

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    /** @return array{0: Order, 1: Payment} */
    private function order(Tenant $tenant, string $menuName, int $price, int $qty, bool $settle = true): array
    {
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'name' => $menuName, 'base_price' => $price, 'stock_qty' => 50]);
        $session = new CustomerSession;
        $session->forceFill(['canteen_id' => $tenant->canteen_id, 'session_token_hash' => OpaqueToken::issue(32)['hash'], 'status' => 'active', 'expires_at' => now()->addHours(4)])->save();
        app(CartService::class)->add($session, $menu->id, $qty);
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;
        $payment = app(PaymentService::class)->initiate($order);
        if ($settle) {
            app(PaymentService::class)->confirmSandbox($payment);
        }

        return [$order->fresh(), $payment->fresh()];
    }

    private function period(): array
    {
        return app(TenantReconciliation::class)->period($this->tenant->id, CarbonImmutable::now('Asia/Jakarta'));
    }

    public function test_period_summary_and_ledger_rows_with_payment_reference(): void
    {
        [$first, $firstPayment] = $this->order($this->tenant, 'Nasi Ayam Bakar', 18000, 1);
        [$second] = $this->order($this->tenant, 'Mie Ayam Spesial', 23000, 2);

        $recon = $this->period();

        $this->assertSame(64000, $recon['gross']);
        $this->assertSame(6400, $recon['commission']);
        $this->assertSame(10.0, $recon['commission_rate']);
        $this->assertSame(0, $recon['other']);
        $this->assertSame(57600, $recon['net']);
        $this->assertSame(57600, $recon['available']);
        $this->assertTrue($recon['matches']);
        $this->assertCount(2, $recon['rows']);

        $row = collect($recon['rows'])->firstWhere('order_id', $first->id);
        $this->assertSame($firstPayment->payment_reference, $row['reference']);
        $this->assertSame([18000, 1800, 16200], [$row['gross'], $row['commission'], $row['net']]);
        $this->assertSame($second->order_number, collect($recon['rows'])->firstWhere('order_id', $second->id)['order_number']);
    }

    public function test_needs_review_payment_is_flagged_and_not_counted(): void
    {
        $this->order($this->tenant, 'Nasi Ayam Bakar', 18000, 1);
        [$review, $payment] = $this->order($this->tenant, 'Sayur Lodeh', 32000, 1, settle: false);
        DB::table('payments')->where('id', $payment->id)->update(['status' => 'needs_review', 'updated_at' => now()]);

        $recon = $this->period();

        $this->assertSame(18000, $recon['gross']);
        $this->assertSame(16200, $recon['available']);
        $this->assertSame([['at' => $recon['review'][0]['at'], 'reference' => $payment->payment_reference, 'order_id' => $review->id, 'order_number' => $review->order_number, 'gross' => 32000]], $recon['review']);

        Livewire::actingAs($this->member)
            ->test('reporting::reconciliation', ['tenantId' => $this->tenant->id])
            ->assertSee('Perlu peninjauan')
            ->assertSee('tidak dihitung ke saldo')
            ->assertSee($payment->payment_reference);
    }

    public function test_withdrawal_entries_appear_as_debit_and_balance_equals_ledger_sum(): void
    {
        $this->order($this->tenant, 'Nasi Ayam Bakar', 20000, 5); // net 90.000
        $account = (new TenantBankAccount)->forceFill(['tenant_id' => $this->tenant->id, 'bank_code' => 'BCA', 'account_holder' => 'Rina S.', 'account_last4' => '6721', 'account_number_cipher' => 'x', 'status' => 'verified', 'is_primary' => true]);
        $account->save();
        $withdrawal = app(WithdrawalService::class)->request($account, 50000, $this->member);

        $recon = $this->period();

        $this->assertSame(40000, $recon['available']);
        $this->assertSame(50000, $recon['held']);
        $this->assertTrue($recon['matches']);
        $hold = collect($recon['rows'])->firstWhere('kind', 'withdrawal');
        $this->assertSame($withdrawal->reference(), $hold['reference']);
        $this->assertSame(-50000, $hold['net']);
        $this->assertSame('Penarikan dana (ditahan)', $hold['label']);
    }

    public function test_drill_down_shows_only_this_tenants_items(): void
    {
        [$order] = $this->order($this->tenant, 'Nasi Ayam Bakar', 18000, 2);
        $other = Tenant::factory()->create(['canteen_id' => $this->canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $other->id, 'commission_rate' => 0.10, 'valid_from' => now()->subYear(), 'valid_to' => null]);
        [$foreignOrder] = $this->order($other, 'Bakso', 15000, 1);

        Livewire::actingAs($this->member)
            ->test('reporting::reconciliation', ['tenantId' => $this->tenant->id])
            ->call('open', $order->id)
            ->assertSee('Rincian pesanan #'.$order->order_number)
            ->assertSee('2× Nasi Ayam Bakar');

        $this->assertNull(app(TenantReconciliation::class)->orderDetail($this->tenant->id, $foreignOrder->id));
    }

    public function test_page_renders_for_member_and_is_forbidden_for_outsider(): void
    {
        $this->actingAs($this->member)->get(route('tenant.reconciliation', ['tenant' => $this->tenant->slug]))
            ->assertOk()
            ->assertSeeLivewire('reporting::reconciliation');

        $outsider = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        Livewire::actingAs($outsider)->test('reporting::reconciliation', ['tenantId' => $this->tenant->id])->assertForbidden();
    }
}
