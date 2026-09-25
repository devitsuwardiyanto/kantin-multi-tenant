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
use App\Modules\Payments\Notifications\WithdrawalReviewed;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\WithdrawalService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UC-23 Verifikasi Pencairan Dana: rincian + kesesuaian ledger, setujui WAJIB bukti transfer
 * (debit ledger, hold dilepas, audit, tenant diberi tahu), idempoten terhadap persetujuan ganda,
 * tolak WAJIB alasan (dana kembali), ledger tidak sesuai → investigasi & tidak dapat disetujui.
 */
class WithdrawalVerificationUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private Canteen $canteen;

    private Tenant $tenant;

    private User $member;

    private User $manager;

    private Withdrawal $withdrawal;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->canteen = Canteen::factory()->create();
        $this->tenant = Tenant::factory()->create(['canteen_id' => $this->canteen->id, 'display_name' => 'Warung Bu Rina']);
        CommissionScheme::factory()->create(['tenant_id' => $this->tenant->id, 'commission_rate' => 0.10, 'valid_from' => now()->subYear(), 'valid_to' => null]);
        $menu = Menu::factory()->create(['tenant_id' => $this->tenant->id, 'base_price' => 50000, 'stock_qty' => 50]);

        $session = new CustomerSession;
        $session->forceFill(['canteen_id' => $this->canteen->id, 'session_token_hash' => OpaqueToken::issue(32)['hash'], 'status' => 'active', 'expires_at' => now()->addHours(4)])->save();
        app(CartService::class)->add($session, $menu->id, 3);
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;
        app(PaymentService::class)->confirmSandbox(app(PaymentService::class)->initiate($order)); // saldo 135.000

        $account = (new TenantBankAccount)->forceFill(['tenant_id' => $this->tenant->id, 'bank_code' => 'BCA', 'account_holder' => 'Rina S.', 'account_last4' => '6721', 'account_number_cipher' => 'x', 'status' => 'verified', 'is_primary' => true]);
        $account->save();

        $this->member = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $this->member->id, 'tenant_id' => $this->tenant->id, 'role' => 'owner']);
        $this->manager = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        UserCanteenRole::create(['user_id' => $this->manager->id, 'canteen_id' => $this->canteen->id, 'role' => 'manager']);

        $this->withdrawal = app(WithdrawalService::class)->request($account, 100000, $this->member);
    }

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    public function test_detail_shows_amount_held_funds_account_and_ledger_match(): void
    {
        $this->actingAs($this->manager)->get(route('admin.withdrawals.index'))
            ->assertOk()
            ->assertSeeLivewire('payments::withdrawal-review')
            ->assertSee($this->withdrawal->reference().' · Warung Bu Rina')
            ->assertSee('Rp100.000 ✓ cukup')
            ->assertSee('BCA ••••6721 · Rina S.')
            ->assertSee('Cocok — 3 entri ✓');
    }

    public function test_approve_requires_proof_then_debits_ledger_releases_hold_and_notifies_tenant(): void
    {
        Notification::fake();

        Livewire::actingAs($this->manager)
            ->test('payments::withdrawal-review')
            ->call('approve', $this->withdrawal->id)
            ->assertHasErrors('proof')
            ->set('proof', UploadedFile::fake()->create('bukti.pdf', 200, 'application/pdf'))
            ->call('approve', $this->withdrawal->id)
            ->assertHasNoErrors()
            ->assertSee('ditandai dicairkan');

        $withdrawal = $this->withdrawal->fresh();
        $this->assertSame('paid', $withdrawal->status);
        $this->assertNull($withdrawal->active_tenant_lock);
        Storage::disk('local')->assertExists($withdrawal->transfer_proof_path);
        $balance = TenantBalance::query()->find($this->tenant->id);
        $this->assertSame([35000, 0], [(int) $balance->available_amount, (int) $balance->held_amount]);
        $this->assertDatabaseHas('ledger_entries', ['withdrawal_id' => $withdrawal->id, 'type' => 'withdrawal_debit', 'held_delta' => -100000]);
        $this->assertDatabaseHas('audit_logs', ['entity' => 'withdrawal', 'entity_id' => $withdrawal->id, 'action' => 'paid']);
        Notification::assertSentTo($this->member, WithdrawalReviewed::class);

        $this->actingAs($this->manager)->get(route('admin.withdrawals.proof', $withdrawal->id))->assertOk();
    }

    public function test_second_approval_is_idempotent(): void
    {
        $service = app(WithdrawalService::class);
        $service->approve($this->withdrawal, $this->manager, 'withdrawal-proofs/a.pdf');
        $again = $service->approve($this->withdrawal, $this->manager, 'withdrawal-proofs/b.pdf');

        $this->assertSame('paid', $again->status);
        $this->assertSame('withdrawal-proofs/a.pdf', $again->transfer_proof_path);
        $this->assertSame(1, DB::table('ledger_entries')->where('withdrawal_id', $this->withdrawal->id)->where('type', 'withdrawal_debit')->count());
        $this->assertSame(0, (int) TenantBalance::query()->find($this->tenant->id)->held_amount);
    }

    public function test_reject_requires_reason_and_returns_held_funds(): void
    {
        Notification::fake();

        Livewire::actingAs($this->manager)
            ->test('payments::withdrawal-review')
            ->call('reject', $this->withdrawal->id)
            ->assertSee('Alasan penolakan wajib diisi.')
            ->set('note', 'Rekening belum terverifikasi')
            ->call('reject', $this->withdrawal->id)
            ->assertSee('ditolak; dana dikembalikan');

        $withdrawal = $this->withdrawal->fresh();
        $this->assertSame('rejected', $withdrawal->status);
        $this->assertSame('Rekening belum terverifikasi', $withdrawal->review_note);
        $balance = TenantBalance::query()->find($this->tenant->id);
        $this->assertSame([135000, 0], [(int) $balance->available_amount, (int) $balance->held_amount]);
        Notification::assertSentTo($this->member, WithdrawalReviewed::class);

        $this->actingAs($this->member)->get(route('tenant.withdrawals', ['tenant' => $this->tenant->slug]))
            ->assertSee('Ditolak')
            ->assertSee('Rekening belum terverifikasi');
    }

    public function test_ledger_mismatch_marks_investigation_and_blocks_approval(): void
    {
        DB::table('tenant_balances')->where('tenant_id', $this->tenant->id)->increment('available_amount', 5000); // saldo ≠ ledger

        try {
            app(WithdrawalService::class)->approve($this->withdrawal, $this->manager, 'withdrawal-proofs/a.pdf');
            $this->fail('ledger tidak sesuai harus menolak persetujuan');
        } catch (WithdrawalException $e) {
            $this->assertStringContainsString('ditandai untuk investigasi', $e->getMessage());
        }

        $this->assertSame('investigation', $this->withdrawal->fresh()->status);
        $this->assertSame(0, DB::table('ledger_entries')->where('type', 'withdrawal_debit')->count());
        $this->assertDatabaseHas('audit_logs', ['entity' => 'withdrawal', 'entity_id' => $this->withdrawal->id, 'action' => 'investigation']);

        $this->expectException(WithdrawalException::class);
        app(WithdrawalService::class)->approve($this->withdrawal->fresh(), $this->manager, 'withdrawal-proofs/a.pdf');
    }

    public function test_other_canteen_manager_cannot_view_or_act(): void
    {
        $otherManager = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        UserCanteenRole::create(['user_id' => $otherManager->id, 'canteen_id' => Canteen::factory()->create()->id, 'role' => 'manager']);

        Livewire::actingAs($otherManager)
            ->test('payments::withdrawal-review')
            ->assertDontSee('Warung Bu Rina')
            ->call('select', $this->withdrawal->id)
            ->assertForbidden();

        app(WithdrawalService::class)->approve($this->withdrawal, $this->manager, 'withdrawal-proofs/a.pdf');
        $this->actingAs($otherManager)->get(route('admin.withdrawals.proof', $this->withdrawal->id))->assertNotFound();
    }
}
