<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\Menu;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Konfigurasi modifier oleh tenant (prasyarat UC-04): grup min/maks, opsi + selisih harga,
 * opsi habis, pemasangan grup ke menu — seluruhnya dibatasi pada tenant anggota.
 */
class ModifierManagerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: User, 2: Menu} */
    private function tenantWithMember(): array
    {
        $tenant = Tenant::factory()->create(['canteen_id' => Canteen::factory()->create()->id]);
        $user = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'role' => 'operator']);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Es Kopi Susu']);

        return [$tenant, $user, $menu];
    }

    public function test_tenant_configures_group_options_and_attaches_group_to_menu(): void
    {
        [$tenant, $user, $menu] = $this->tenantWithMember();

        $component = Livewire::actingAs($user)->test('catalog::modifier-manager', ['tenantId' => $tenant->id])
            ->set('groupName', 'Topping')->set('minSelect', 0)->set('maxSelect', 2)
            ->call('createGroup')->assertHasNoErrors();

        $group = ModifierGroup::query()->withoutGlobalScope('tenant')->where('name', 'Topping')->firstOrFail();
        $this->assertSame([$tenant->id, 0, 2], [$group->tenant_id, $group->min_select, $group->max_select]);

        $component->set("optionName.{$group->id}", 'Extra shot')->set("optionPrice.{$group->id}", 3000)
            ->call('addOption', $group->id)->assertHasNoErrors()
            ->call('toggleAttachment', $group->id, $menu->id)
            ->assertSee('Extra shot · +Rp3.000');

        $option = ModifierOption::query()->withoutGlobalScope('tenant')->where('name', 'Extra shot')->firstOrFail();
        $this->assertSame($tenant->id, $option->tenant_id);
        $this->assertTrue($menu->modifierGroups()->withoutGlobalScope('tenant')->whereKey($group->id)->exists());

        $component->call('toggleOption', $option->id)->assertSee('HABIS');
        $this->assertFalse($option->fresh()->is_available);
        $this->assertDatabaseHas('audit_logs', ['entity' => 'modifier_option', 'entity_id' => $option->id, 'action' => 'availability_changed']);
    }

    public function test_max_must_not_be_less_than_min(): void
    {
        [$tenant, $user] = $this->tenantWithMember();

        Livewire::actingAs($user)->test('catalog::modifier-manager', ['tenantId' => $tenant->id])
            ->set('groupName', 'Ukuran')->set('minSelect', 2)->set('maxSelect', 1)
            ->call('createGroup')
            ->assertHasErrors(['maxSelect' => 'gte']);
    }

    public function test_group_of_other_tenant_cannot_be_attached(): void
    {
        [$tenant, $user, $menu] = $this->tenantWithMember();
        $foreign = ModifierGroup::factory()->create();

        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($user)->test('catalog::modifier-manager', ['tenantId' => $tenant->id])
            ->call('toggleAttachment', $foreign->id, $menu->id);
    }

    public function test_non_member_is_forbidden(): void
    {
        [$tenant] = $this->tenantWithMember();
        $outsider = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);

        Livewire::actingAs($outsider)->test('catalog::modifier-manager', ['tenantId' => $tenant->id])
            ->assertForbidden();
    }

    public function test_modifier_page_is_served_from_catalog_module(): void
    {
        [$tenant, $user] = $this->tenantWithMember();

        $this->actingAs($user)->get(route('tenant.modifier-manager', $tenant))
            ->assertOk()
            ->assertSeeLivewire('catalog::modifier-manager');
    }
}
