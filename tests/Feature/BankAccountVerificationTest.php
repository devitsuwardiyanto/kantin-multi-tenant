<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Canteen;
use App\Models\Tenant;
use App\Models\TenantBankAccount;
use App\Models\User;
use App\Models\UserCanteenRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Temuan uji penerimaan Pertemuan 14: rekening hasil onboarding (UC-21) berstatus unverified,
 * padahal UC-20 mensyaratkan rekening terverifikasi. Pengelola kini memverifikasi/menolak dan
 * memilih rekening utama langsung dari halaman tenant.
 */
class BankAccountVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Tenant, 2: TenantBankAccount} */
    private function managerWithAccount(): array
    {
        $canteen = Canteen::factory()->create();
        $manager = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        UserCanteenRole::create(['user_id' => $manager->id, 'canteen_id' => $canteen->id, 'role' => 'manager']);
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $account = $this->account($tenant, ['status' => 'unverified', 'is_primary' => true]);

        return [$manager, $tenant, $account];
    }

    /** @param array<string, mixed> $attributes */
    private function account(Tenant $tenant, array $attributes = []): TenantBankAccount
    {
        $account = (new TenantBankAccount)->forceFill(array_merge([
            'tenant_id' => $tenant->id, 'bank_code' => 'BCA', 'account_holder' => 'Rina Sari',
            'account_last4' => '6721', 'account_number_cipher' => '1234566721', 'status' => 'unverified', 'is_primary' => false,
        ], $attributes));
        $account->save();

        return $account;
    }

    public function test_manager_sees_verify_button_and_verifies_account(): void
    {
        [$manager, $tenant, $account] = $this->managerWithAccount();

        $this->actingAs($manager)->get(route('admin.tenants.edit', $tenant))
            ->assertOk()
            ->assertSee('data-test="bank-verify-'.$account->id.'"', false)
            ->assertSee('Tolak');

        $this->post(route('admin.tenants.bank.verify', [$tenant, $account]), ['approve' => '1'])
            ->assertRedirect(route('admin.tenants.edit', $tenant));

        $this->assertSame('verified', $account->fresh()->status);
        $this->assertTrue(AuditLog::query()->where(['entity' => 'tenant_bank_account', 'action' => 'verified'])->exists());
        $this->get(route('admin.tenants.edit', $tenant))->assertDontSee('data-test="bank-verify-'.$account->id.'"', false);
    }

    public function test_manager_rejects_and_switches_primary_to_verified_account(): void
    {
        [$manager, $tenant, $account] = $this->managerWithAccount();
        $second = $this->account($tenant, ['bank_code' => 'BNI', 'account_last4' => '9981', 'status' => 'verified']);

        $this->actingAs($manager)->post(route('admin.tenants.bank.verify', [$tenant, $account]), ['approve' => '0']);
        $this->assertSame('rejected', $account->fresh()->status);

        $this->get(route('admin.tenants.edit', $tenant))->assertSee('Jadikan utama');
        $this->post(route('admin.tenants.bank.primary', [$tenant, $second]))->assertRedirect();

        $this->assertTrue($second->fresh()->is_primary);
        $this->assertFalse($account->fresh()->is_primary);
    }

    public function test_account_of_other_tenant_or_other_canteen_is_rejected(): void
    {
        [$manager, $tenant] = $this->managerWithAccount();
        $foreignTenant = Tenant::factory()->create();
        $foreignAccount = $this->account($foreignTenant);

        $this->actingAs($manager)->post(route('admin.tenants.bank.verify', [$tenant, $foreignAccount]), ['approve' => '1'])->assertNotFound();
        $this->post(route('admin.tenants.bank.verify', [$foreignTenant, $foreignAccount]), ['approve' => '1'])->assertForbidden();
        $this->assertSame('unverified', $foreignAccount->fresh()->status);
    }
}
