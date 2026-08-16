<?php

namespace Tests\Feature;

use App\Jobs\CountTenantMenus;
use App\Models\Canteen;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use App\Modules\Catalog\Services\PublicCatalogQuery;
use App\Policies\MenuPolicy;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matriks isolasi tenant (allow/deny) A vs B: global scope, scoped binding, policy,
 * public bypass terkontrol, dan context pada job. Dijalankan di MariaDB nyata.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: Collection<int, Menu>} */
    private function tenantWithMenus(int $count, ?Canteen $canteen = null, string $status = 'active'): array
    {
        $canteen ??= Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id, 'status' => $status]);
        $category = MenuCategory::factory()->create(['tenant_id' => $tenant->id]);
        $menus = Menu::factory()->count($count)->create(['tenant_id' => $tenant->id, 'category_id' => $category->id]);

        return [$tenant, $menus];
    }

    private function memberOf(Tenant $tenant): User
    {
        $user = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'role' => 'operator']);

        return $user;
    }

    public function test_global_scope_returns_only_active_tenant_rows(): void
    {
        [$a] = $this->tenantWithMenus(2);
        $this->tenantWithMenus(3); // tenant B

        app(TenantContext::class)->set($a);
        $this->assertSame(2, Menu::query()->count());
        $this->assertTrue(Menu::query()->get()->every(fn (Menu $m) => $m->tenant_id === $a->id));
    }

    public function test_without_context_scope_is_not_applied(): void
    {
        $this->tenantWithMenus(2);
        $this->tenantWithMenus(3);

        // Tanpa context, scope tidak memfilter (alur internal SELALU mengisi context).
        $this->assertSame(5, Menu::query()->count());
    }

    public function test_context_is_reset_between_requests(): void
    {
        [$a] = $this->tenantWithMenus(1);
        app(TenantContext::class)->set($a);
        $this->assertTrue(app(TenantContext::class)->has());

        // Simulasi lifecycle request/job berikutnya.
        $this->app->forgetScopedInstances();
        $this->assertFalse(app(TenantContext::class)->has(), 'TenantContext bocor antar request/job');
    }

    public function test_tenant_id_is_autofilled_on_create_within_context(): void
    {
        [$a] = $this->tenantWithMenus(0);
        app(TenantContext::class)->set($a);

        $category = MenuCategory::create(['name' => 'Auto Fill']);
        $this->assertSame($a->id, $category->tenant_id);
    }

    public function test_menu_index_returns_only_own_tenant(): void
    {
        [$a, $menusA] = $this->tenantWithMenus(2);
        $this->tenantWithMenus(3); // B

        $this->actingAs($this->memberOf($a));
        $response = $this->getJson(route('tenant.menus.index', ['tenant' => $a->slug]));

        $response->assertOk();
        $this->assertCount(2, $response->json('menus'));
    }

    public function test_scoped_binding_blocks_cross_tenant_menu(): void
    {
        [$a] = $this->tenantWithMenus(1);
        [, $menusB] = $this->tenantWithMenus(1);
        $menuB = $menusB->first();

        $this->actingAs($this->memberOf($a));
        // Menu tenant B diakses lewat route tenant A -> tidak ditemukan (scoped binding + scope).
        $this->getJson(route('tenant.menus.show', ['tenant' => $a->slug, 'menu' => $menuB->id]))
            ->assertNotFound();
    }

    public function test_member_can_toggle_own_menu(): void
    {
        [$a, $menusA] = $this->tenantWithMenus(1);
        $menuA = $menusA->first();

        $this->actingAs($this->memberOf($a));
        $this->patchJson(route('tenant.menus.update', ['tenant' => $a->slug, 'menu' => $menuA->id]), [
            'is_available' => false,
        ])->assertOk()->assertJson(['ok' => true, 'is_available' => false]);

        $this->assertFalse($menuA->refresh()->is_available);
    }

    public function test_policy_denies_cross_tenant_update(): void
    {
        [$a] = $this->tenantWithMenus(0);
        [, $menusB] = $this->tenantWithMenus(1);
        $menuB = $menusB->first();

        app(TenantContext::class)->set($a); // aktif = A
        $this->assertFalse((new MenuPolicy)->update(new User, $menuB), 'Policy harus menolak menu tenant lain');
    }

    public function test_public_catalog_query_no_cross_canteen_or_inactive_leak(): void
    {
        $canteen1 = Canteen::factory()->create();
        $this->tenantWithMenus(2, $canteen1);                       // aktif, available
        $this->tenantWithMenus(1, $canteen1, status: 'suspended');  // suspended -> tidak tampil
        $canteen2 = Canteen::factory()->create();
        $this->tenantWithMenus(4, $canteen2);                       // canteen lain -> tidak tampil

        $menus = app(PublicCatalogQuery::class)->forCanteen($canteen1);

        $this->assertCount(2, $menus, 'Hanya menu tenant aktif dari canteen ini');
    }

    public function test_job_sets_and_clears_context_per_tenant(): void
    {
        [$a] = $this->tenantWithMenus(2);
        [$b] = $this->tenantWithMenus(3);

        $jobA = new CountTenantMenus($a->id);
        $jobA->handle(app(TenantContext::class));
        $this->assertSame(2, $jobA->result);
        $this->assertFalse(app(TenantContext::class)->has(), 'Context harus dibersihkan setelah job');

        $jobB = new CountTenantMenus($b->id);
        $jobB->handle(app(TenantContext::class));
        $this->assertSame(3, $jobB->result); // tidak terkontaminasi tenant A
    }
}
