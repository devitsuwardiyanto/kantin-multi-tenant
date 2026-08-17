<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TenantMenuManagerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: User} */
    private function tenantWithMember(): array
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $user = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'role' => 'operator']);

        return [$tenant, $user];
    }

    public function test_member_creates_menu_with_tenant_id_from_context(): void
    {
        [$tenant, $user] = $this->tenantWithMember();
        $category = MenuCategory::factory()->create(['tenant_id' => $tenant->id]);

        Livewire::actingAs($user)
            ->test('tenant.menu-manager', ['tenantId' => $tenant->id])
            ->set('name', 'Soto Ayam')
            ->set('categoryId', $category->id)
            ->set('basePrice', 15000)
            ->set('stockQty', 20)
            ->call('createMenu')
            ->assertHasNoErrors();

        $menu = Menu::withoutGlobalScope('tenant')->where('name', 'Soto Ayam')->firstOrFail();
        $this->assertSame($tenant->id, $menu->tenant_id, 'tenant_id harus dari context, bukan input');
    }

    public function test_toggle_and_restock_are_atomic_and_audited(): void
    {
        [$tenant, $user] = $this->tenantWithMember();
        $category = MenuCategory::factory()->create(['tenant_id' => $tenant->id]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $category->id, 'stock_qty' => 5, 'is_available' => true]);

        Livewire::actingAs($user)
            ->test('tenant.menu-manager', ['tenantId' => $tenant->id])
            ->call('toggle', $menu->id)
            ->call('restock', $menu->id, 10);

        $menu->refresh();
        $this->assertFalse($menu->is_available);
        $this->assertSame(15, $menu->stock_qty);
        // movement tercatat
        $this->assertSame(1, DB::table('menu_stock_movements')->where('menu_id', $menu->id)->count());
        // audit tercatat (toggle + restock)
        $this->assertTrue(DB::table('audit_logs')->where('entity', 'menu')->where('action', 'toggle_availability')->exists());
    }

    public function test_tampered_tenant_id_for_non_member_is_forbidden(): void
    {
        [$tenantA, $userA] = $this->tenantWithMember();
        $canteenB = Canteen::factory()->create();
        $tenantB = Tenant::factory()->create(['canteen_id' => $canteenB->id]);

        // userA bukan anggota tenantB -> booted() harus abort 403
        Livewire::actingAs($userA)
            ->test('tenant.menu-manager', ['tenantId' => $tenantB->id])
            ->assertForbidden();
    }
}
