<?php

namespace Tests\Feature;

use App\Models\User;
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

    public function test_tenant_operator_can_open_tenant_dashboard(): void
    {
        $this->actingAs($this->user('tenant'));
        $this->get(route('tenant.dashboard', ['tenant' => 'demo']))->assertOk();
    }

    public function test_admin_can_open_admin_dashboard(): void
    {
        $this->actingAs($this->user('admin'));
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_wrong_role_is_forbidden_not_empty_page(): void
    {
        // operator tenant tidak boleh masuk konteks admin, dan sebaliknya
        $this->actingAs($this->user('tenant'));
        $this->get(route('admin.dashboard'))->assertForbidden();

        $this->actingAs($this->user('admin'));
        $this->get(route('tenant.dashboard', ['tenant' => 'demo']))->assertForbidden();
    }

    public function test_user_without_role_is_forbidden_on_internal_contexts(): void
    {
        $this->actingAs($this->user(null));
        $this->get(route('tenant.dashboard', ['tenant' => 'demo']))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_suspended_operator_is_forbidden(): void
    {
        $this->actingAs($this->user('tenant', status: 'suspended'));
        $this->get(route('tenant.dashboard', ['tenant' => 'demo']))->assertForbidden();
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
