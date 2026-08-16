<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use App\Modules\Admin\AdminServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Kitchen\KitchenServiceProvider;
use App\Modules\Ordering\OrderingServiceProvider;
use App\Modules\Payments\PaymentsServiceProvider;
use App\Modules\Reporting\ReportingServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ServiceProvider;
use Tests\TestCase;

class ModularFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function user(?string $role, string $status = 'active'): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => $status,
            'email_verified_at' => now(),
        ]);
    }

    public function test_customer_route_is_public(): void
    {
        $this->get(route('customer.home', ['canteen' => 'demo']))->assertOk();
    }

    public function test_guest_is_redirected_to_login_on_tenant_and_admin(): void
    {
        $this->get(route('tenant.dashboard', ['tenant' => 'demo']))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    private function tenantWithMember(User $user, string $status = 'active'): Tenant
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id, 'status' => $status]);
        UserTenantRole::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'role' => 'operator']);

        return $tenant;
    }

    public function test_tenant_member_can_open_tenant_dashboard(): void
    {
        $user = $this->user('tenant');
        $this->actingAs($user);
        $tenant = $this->tenantWithMember($user);

        $this->get(route('tenant.dashboard', ['tenant' => $tenant->slug]))->assertOk();
    }

    public function test_admin_can_open_admin_dashboard(): void
    {
        $this->actingAs($this->user('admin'));
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_non_member_is_forbidden_on_tenant_and_admin_contexts(): void
    {
        // operator tenant tidak boleh masuk konteks admin
        $this->actingAs($this->user('tenant'));
        $this->get(route('admin.dashboard'))->assertForbidden();

        // admin (bukan anggota tenant) tidak boleh masuk dashboard tenant
        $admin = $this->user('admin');
        $this->actingAs($admin);
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $this->get(route('tenant.dashboard', ['tenant' => $tenant->slug]))->assertForbidden();
    }

    public function test_user_without_role_is_forbidden_on_internal_contexts(): void
    {
        $user = $this->user(null);
        $this->actingAs($user);
        $this->get(route('admin.dashboard'))->assertForbidden();

        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $this->get(route('tenant.dashboard', ['tenant' => $tenant->slug]))->assertForbidden();
    }

    public function test_suspended_tenant_is_forbidden_even_for_member(): void
    {
        $user = $this->user('tenant');
        $this->actingAs($user);
        $tenant = $this->tenantWithMember($user, status: 'suspended');

        $this->get(route('tenant.dashboard', ['tenant' => $tenant->slug]))->assertForbidden();
    }

    public function test_all_six_module_providers_are_registered(): void
    {
        $providers = array_map('get_class', app()->getProviders(ServiceProvider::class));
        foreach ([
            AdminServiceProvider::class,
            CatalogServiceProvider::class,
            OrderingServiceProvider::class,
            PaymentsServiceProvider::class,
            KitchenServiceProvider::class,
            ReportingServiceProvider::class,
        ] as $p) {
            $this->assertContains($p, $providers, "Provider tidak terdaftar: {$p}");
        }
    }
}
