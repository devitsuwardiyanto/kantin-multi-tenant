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
use App\Models\UserCanteenRole;
use App\Models\UserTenantRole;
use App\Models\Withdrawal;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Exceptions\WithdrawalException;
use App\Modules\Payments\Notifications\WithdrawalRequested;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\WithdrawalService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UC-20 Ajukan Penarikan Dana: minimum Rp100.000, saldo divalidasi di dalam transaksi berkunci,
 * hold append-only, satu pengajuan aktif per tenant (2b), saldo kurang menampilkan saldo terkini
 * (2a), pengelola diberi tahu, audit tercatat; guard basis data mencegah saldo negatif/hold ganda.
 */
class WithdrawalRequestUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private Canteen $canteen;

    private Tenant $tenant;

    private TenantBankAccount $account;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->canteen = Canteen::factory()->create();
        $this->tenant = Tenant::factory()->create(['canteen_id' => $this->canteen->id, 'display_name' => 'Warung Bu Rina']);
        CommissionScheme::factory()->create(['tenant_id' => $this->tenant->id, 'commission_rate' => 0.10, 'valid_from' => now()->subYear(), 'valid_to' => null]);
        $menu = Menu::factory()->create(['tenant_id' => $this->tenant->id, 'base_price' => 50000, 'stock_qty' => 50]);

        $session = new CustomerSession;
        $session->forceFill(['canteen_id' => $this->canteen->id, 'session_token_hash' => OpaqueToken::issue(32)['hash'], 'status' => 'active', 'expires_at' => now()->addHours(4)])->save();
        app(CartService::class)->add($session, $menu->id, 3);
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;
        app(PaymentService::class)->confirmSandbox(app(PaymentService::class)->initiate($order)); // saldo 135.000

        $this->account = (new TenantBankAccount)->forceFill(['tenant_id' => $this->tenant->id, 'bank_code' => 'BCA', 'account_holder' => 'Rina S.', 'account_last4' => '6721', 'account_number_cipher' => 'x', 'status' => 'verified', 'is_primary' => true]);
        $this->account->save();

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

    public function test_request_holds_funds_audits_and_notifies_canteen_reviewers(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        UserCanteenRole::create(['user_id' => $manager->id, 'canteen_id' => $this->canteen->id, 'role' => 'manager']);

        $withdrawal = app(WithdrawalService::class)->request($this->account, 100000, $this->member);

        $this->assertSame('requested', $withdrawal->status);
        $balance = TenantBalance::query()->find($this->tenant->id);
        $this->assertSame([35000, 100000], [(int) $balance->available_amount, (int) $balance->held_amount]);
        $this->assertDatabaseHas('ledger_entries', ['withdrawal_id' => $withdrawal->id, 'type' => 'hold', 'available_delta' => -100000, 'held_delta' => 100000]);
        $this->assertDatabaseHas('audit_logs', ['entity' => 'withdrawal', 'entity_id' => $withdrawal->id, 'action' => 'requested']);
        Notification::assertSentTo($manager, WithdrawalRequested::class);
    }

    public function test_amount_below_minimum_is_rejected(): void
    {
        $this->expectException(WithdrawalException::class);
        $this->expectExceptionMessage('Nominal penarikan minimum Rp100.000.');

        app(WithdrawalService::class)->request($this->account, 99999, $this->member);
    }

    public function test_insufficient_balance_shows_current_available_balance(): void
    {
        Livewire::actingAs($this->member)
            ->test('payments::withdrawal-request', ['tenantId' => $this->tenant->id])
            ->set('amount', 200000)
            ->call('submit')
            ->assertHasErrors('amount')
            ->assertSee('Saldo tersedia tidak mencukupi. Saldo tersedia saat ini: Rp135.000.');

        $this->assertSame(0, Withdrawal::query()->withoutGlobalScope('tenant')->count());
    }

    public function test_second_request_while_one_is_active_is_rejected(): void
    {
        $service = app(WithdrawalService::class);
        $service->request($this->account, 100000, $this->member);

        try {
            $service->request($this->account, 100000, $this->member);
            $this->fail('pengajuan kedua harus ditolak');
        } catch (WithdrawalException $e) {
            $this->assertStringContainsString('pengajuan penarikan yang berjalan', $e->getMessage());
        }

        $this->assertSame(1, DB::table('ledger_entries')->where('type', 'hold')->count());
    }

    public function test_database_guards_prevent_negative_balance_and_double_active_hold(): void
    {
        $withdrawal = app(WithdrawalService::class)->request($this->account, 100000, $this->member);

        // UNIQUE active_tenant_lock: pengajuan aktif kedua tidak dapat disisipkan (mis. dua request bersamaan).
        try {
            DB::table('withdrawals')->insert([
                'tenant_id' => $this->tenant->id, 'bank_account_id' => $this->account->id, 'requested_by' => $this->member->id,
                'idempotency_key' => 'wd-race', 'amount' => 30000, 'status' => 'requested', 'active_tenant_lock' => $this->tenant->id,
            ]);
            $this->fail('hold ganda harus ditolak basis data');
        } catch (UniqueConstraintViolationException) {
        }

        // CHECK saldo non-negatif: pemotongan melebihi saldo gagal.
        $this->expectException(QueryException::class);
        DB::table('tenant_balances')->where('tenant_id', $this->tenant->id)->decrement('available_amount', 35001);
        $this->assertSame('requested', $withdrawal->status);
    }

    public function test_page_shows_balance_account_and_history(): void
    {
        app(WithdrawalService::class)->request($this->account, 100000, $this->member);

        $this->actingAs($this->member)->get(route('tenant.withdrawals', ['tenant' => $this->tenant->slug]))
            ->assertOk()
            ->assertSeeLivewire('payments::withdrawal-request')
            ->assertSee('BCA ····6721 a.n. Rina S. ✓ terverifikasi')
            ->assertSee('Rp35.000')
            ->assertSee('Tertahan (pengajuan aktif): Rp100.000')
            ->assertSee('Menunggu verifikasi pengelola');
    }

    public function test_unverified_account_cannot_request(): void
    {
        $this->account->forceFill(['status' => 'unverified'])->save();

        Livewire::actingAs($this->member)
            ->test('payments::withdrawal-request', ['tenantId' => $this->tenant->id])
            ->assertSee('Belum ada rekening terverifikasi')
            ->set('amount', 100000)
            ->call('submit')
            ->assertHasErrors('amount');
    }
}
