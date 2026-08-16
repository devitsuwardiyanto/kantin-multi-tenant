<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\Fixtures\Modules\Probe\ProbeServiceProvider;
use Tests\TestCase;

/**
 * Konvensi modular monolith (vertical slice): modul memiliki route, view, dan komponen
 * Livewire-nya sendiri; middleware portal tetap terpusat di PortalRoutes.
 */
class ModuleConventionTest extends TestCase
{
    use RefreshDatabase;

    private function user(?string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function tenantWithMember(User $user): Tenant
    {
        $tenant = Tenant::factory()->create(['canteen_id' => Canteen::factory()->create()->id]);
        UserTenantRole::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'role' => 'operator']);

        return $tenant;
    }

    /**
     * Daftarkan modul tiruan setelah boot. Pada alur normal Laravel menyegarkan indeks nama route
     * setelah semua provider boot; di sini dilakukan manual karena registrasi terjadi belakangan.
     */
    private function registerProbeModule(): void
    {
        $this->app->register(ProbeServiceProvider::class);
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_every_module_registers_its_own_view_namespace(): void
    {
        $hints = View::getFinder()->getHints();

        foreach (['Admin', 'Catalog', 'Ordering', 'Payments', 'Kitchen', 'Reporting'] as $module) {
            $alias = strtolower($module);
            $this->assertArrayHasKey($alias, $hints, "Namespace view '{$alias}::' tidak terdaftar");
            $this->assertContains(app_path("Modules/{$module}/resources/views"), $hints[$alias]);
        }
    }

    public function test_module_route_file_is_loaded_inside_portal_group(): void
    {
        $this->registerProbeModule();

        $route = Route::getRoutes()->getByName('tenant.probe');

        $this->assertNotNull($route, 'Route modul tidak dimuat oleh provider');
        $this->assertSame('tenant/{tenant}/probe', $route->uri());
        foreach (['web', 'auth', 'verified', 'tenant'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
        $this->assertTrue($route->enforcesScopedBindings());
    }

    public function test_module_route_is_guarded_like_core_portal_routes(): void
    {
        $this->registerProbeModule();
        $member = $this->user('tenant');
        $tenant = $this->tenantWithMember($member);
        $url = route('tenant.probe', ['tenant' => $tenant->slug]);

        $this->get($url)->assertRedirect(route('login'));

        // Bukan anggota tenant (termasuk admin) → 403 dari SetTenantContext.
        $this->actingAs($this->user('admin'));
        $this->get($url)->assertForbidden();
        $this->actingAs($this->user('tenant'));
        $this->get($url)->assertForbidden();

        $this->actingAs($member);
        $this->get($url)->assertOk()->assertSee('Probe page')->assertSee('Probe count: 0');
    }

    public function test_module_livewire_component_is_resolved_through_module_namespace(): void
    {
        $this->registerProbeModule();

        Livewire::test('probe::counter')
            ->assertSee('Probe count: 0')
            ->call('increment')
            ->assertSee('Probe count: 1');
    }

    public function test_every_portal_route_carries_the_portal_middleware(): void
    {
        $this->registerProbeModule();

        $portals = [
            'tenant.' => ['web', 'auth', 'verified', 'tenant'],
            'admin.' => ['web', 'auth', 'verified', 'role:admin'],
            'customer.' => ['web'],
        ];

        $checked = 0;
        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            foreach ($portals as $prefix => $required) {
                if (str_starts_with((string) $route->getName(), $prefix)) {
                    foreach ($required as $middleware) {
                        $this->assertContains($middleware, $route->gatherMiddleware(), "{$route->getName()} tanpa {$middleware}");
                    }
                    $checked++;
                }
            }
        }

        $this->assertGreaterThanOrEqual(4, $checked);
    }
}
