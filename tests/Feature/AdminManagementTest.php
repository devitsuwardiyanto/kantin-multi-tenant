<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\Tenant;
use App\Models\TenantBalance;
use App\Models\TenantBankAccount;
use App\Models\User;
use App\Models\UserCanteenRole;
use App\Modules\Admin\Services\AssignTenantRole;
use App\Modules\Admin\Services\ChangeCommissionSchedule;
use App\Modules\Admin\Services\ManageBankAccount;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function managerFor(Canteen $canteen): User
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        UserCanteenRole::create(['user_id' => $user->id, 'canteen_id' => $canteen->id, 'role' => 'manager']);

        return $user;
    }

    public function test_only_canteen_manager_can_see_tenant_index(): void
    {
        $canteen = Canteen::factory()->create();
        $this->actingAs($this->managerFor($canteen));
        $this->get(route('admin.tenants.index'))->assertOk();

        // admin tanpa peran canteen -> 403 (policy viewAny)
        $outsider = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($outsider);
        $this->get(route('admin.tenants.index'))->assertForbidden();
    }

    public function test_creating_tenant_also_creates_active_commission_and_balance(): void
    {
        $canteen = Canteen::factory()->create();
        $this->actingAs($this->managerFor($canteen));

        $this->post(route('admin.tenants.store'), [
            'display_name' => 'Bakso Enak', 'code' => 'BAKSO', 'slug' => 'bakso', 'commission_rate' => '15',
        ])->assertRedirect();

        $tenant = Tenant::where('code', 'BAKSO')->firstOrFail();
        $this->assertSame(1, CommissionScheme::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->whereNull('valid_to')->count());
        $this->assertTrue(TenantBalance::whereKey($tenant->id)->exists());

        // Halaman create & edit admin benar-benar merender.
        $this->get(route('admin.tenants.create'))->assertOk();
        $this->get(route('admin.tenants.edit', $tenant))->assertOk()->assertSee('Skema Komisi');
    }

    public function test_tenant_code_is_unique_per_canteen(): void
    {
        $canteen = Canteen::factory()->create();
        $manager = $this->managerFor($canteen);
        Tenant::factory()->create(['canteen_id' => $canteen->id, 'code' => 'DUP']);

        $this->actingAs($manager);
        $this->post(route('admin.tenants.store'), [
            'display_name' => 'X', 'code' => 'DUP', 'slug' => 'x', 'commission_rate' => '10',
        ])->assertSessionHasErrors('code');
    }

    public function test_commission_schedule_is_effective_dated(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.15, 'valid_from' => now()->subMonth(), 'valid_to' => null]);

        app(ChangeCommissionSchedule::class)->handle($tenant, 0.20, now()->addDay());

        $schemes = CommissionScheme::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->orderBy('valid_from')->get();
        $this->assertCount(2, $schemes);
        $this->assertNotNull($schemes[0]->valid_to, 'versi lama harus ditutup');
        $this->assertNull($schemes[1]->valid_to, 'versi baru harus aktif');
    }

    public function test_two_open_commission_schemes_rejected_by_db_guard(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $row = fn () => [
            'tenant_id' => $tenant->id, 'commission_rate' => 0.15,
            'valid_from' => now(), 'valid_to' => null, 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('commission_schemes')->insert($row());

        $this->expectException(QueryException::class);
        DB::table('commission_schemes')->insert($row());
    }

    public function test_bank_account_is_stored_encrypted_with_last4(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);

        $account = app(ManageBankAccount::class)->store($tenant, [
            'bank_code' => 'BCA', 'account_holder' => 'Budi', 'account_number' => '1234567890',
        ]);

        $raw = DB::table('tenant_bank_accounts')->where('id', $account->id)->first();
        $this->assertStringNotContainsString('1234567890', (string) $raw->account_number_cipher, 'nomor mentah bocor di DB');
        $this->assertSame('7890', $raw->account_last4);
        $this->assertSame('1234567890', $account->fresh()->account_number_cipher, 'harus bisa didekripsi kembali');
    }

    public function test_only_one_primary_bank_account_per_tenant(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $svc = app(ManageBankAccount::class);
        $a = $svc->store($tenant, ['bank_code' => 'BCA', 'account_holder' => 'A', 'account_number' => '111111']);
        $b = $svc->store($tenant, ['bank_code' => 'BNI', 'account_holder' => 'B', 'account_number' => '222222']);

        $svc->makePrimary($a);
        $svc->makePrimary($b);

        $this->assertFalse($a->fresh()->is_primary);
        $this->assertTrue($b->fresh()->is_primary);
        $this->assertSame(1, TenantBankAccount::where('tenant_id', $tenant->id)->where('is_primary', true)->count());
    }

    public function test_cannot_remove_last_owner(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $owner = User::factory()->create();
        $svc = app(AssignTenantRole::class);
        $svc->assign($tenant, $owner, 'owner');

        $this->expectException(DomainException::class);
        $svc->remove($tenant, $owner, 'owner');
    }

    public function test_audit_log_is_written_on_sensitive_action(): void
    {
        $canteen = Canteen::factory()->create();
        $this->actingAs($this->managerFor($canteen));

        $this->post(route('admin.tenants.store'), [
            'display_name' => 'Y', 'code' => 'YY', 'slug' => 'yy', 'commission_rate' => '12',
        ])->assertRedirect();

        $this->assertTrue(AuditLog::where('entity', 'tenant')->where('action', 'created')->exists());
    }
}
