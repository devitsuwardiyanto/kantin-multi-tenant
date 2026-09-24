<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UC-13 Kelola Menu & Stok (foto WebP ≤ 2 MB, validasi per kolom, ubah, soft delete, cari/saring)
 * dan UC-14 Tandai Menu Habis (sakelar satu ketukan).
 */
class MenuManagerUseCaseTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: User, 2: MenuCategory} */
    private function tenantWithMember(): array
    {
        $tenant = Tenant::factory()->create(['canteen_id' => Canteen::factory()->create()->id]);
        $user = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'role' => 'operator']);
        $category = MenuCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Makanan utama']);

        return [$tenant, $user, $category];
    }

    public function test_photo_is_compressed_to_webp_and_description_is_saved(): void
    {
        Storage::fake('public');
        [$tenant, $user, $category] = $this->tenantWithMember();

        Livewire::actingAs($user)->test('catalog::menu-manager', ['tenantId' => $tenant->id])
            ->set('name', 'Nasi Ayam Bakar')->set('description', 'Ayam bakar kecap, lalapan')
            ->set('categoryId', $category->id)->set('basePrice', 18000)->set('prepMinutes', 8)->set('stockQty', 20)
            ->set('photo', UploadedFile::fake()->image('ayam.jpg', 1600, 1200))
            ->call('createMenu')->assertHasNoErrors();

        $menu = Menu::query()->withoutGlobalScope('tenant')->where('name', 'Nasi Ayam Bakar')->firstOrFail();
        $this->assertSame('Ayam bakar kecap, lalapan', $menu->description);
        $this->assertSame(8, $menu->prep_minutes);
        $this->assertStringEndsWith('.webp', $menu->photo_path);
        Storage::disk('public')->assertExists($menu->photo_path);
        $this->assertSame('image/webp', mime_content_type(Storage::disk('public')->path($menu->photo_path)));
        [$width] = getimagesize(Storage::disk('public')->path($menu->photo_path));
        $this->assertSame(800, $width, 'sisi terpanjang diperkecil ke 800 px');
    }

    public function test_validation_errors_are_per_field_and_input_is_kept(): void
    {
        Storage::fake('public');
        [$tenant, $user] = $this->tenantWithMember();

        Livewire::actingAs($user)->test('catalog::menu-manager', ['tenantId' => $tenant->id])
            ->set('name', 'Es Teh')->set('basePrice', 0)
            ->set('photo', UploadedFile::fake()->create('besar.jpg', 3000, 'image/jpeg'))
            ->call('createMenu')
            ->assertHasErrors(['basePrice' => 'min', 'categoryId' => 'required', 'photo'])
            ->assertSet('name', 'Es Teh');
    }

    public function test_edit_updates_menu_and_delete_is_soft(): void
    {
        [$tenant, $user, $category] = $this->tenantWithMember();
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $category->id, 'name' => 'Mie Ayam', 'base_price' => 12000]);

        Livewire::actingAs($user)->test('catalog::menu-manager', ['tenantId' => $tenant->id])
            ->call('edit', $menu->id)->assertSet('name', 'Mie Ayam')
            ->set('basePrice', 14000)->call('updateMenu')->assertHasNoErrors()
            ->call('deleteMenu', $menu->id);

        $this->assertSame(14000, Menu::withTrashed()->withoutGlobalScope('tenant')->find($menu->id)->base_price);
        $this->assertSoftDeleted('menus', ['id' => $menu->id]);
    }

    public function test_search_and_category_filter_and_one_tap_toggle(): void
    {
        [$tenant, $user, $category] = $this->tenantWithMember();
        $drink = MenuCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Minuman']);
        $nasi = Menu::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $category->id, 'name' => 'Nasi Ayam Bakar']);
        Menu::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $drink->id, 'name' => 'Es Teh Manis']);

        $component = Livewire::actingAs($user)->test('catalog::menu-manager', ['tenantId' => $tenant->id])
            ->set('search', 'Nasi')->assertSee('Nasi Ayam Bakar')->assertDontSee('Es Teh Manis')
            ->set('search', '')->set('filterCategory', $drink->id)->assertSee('Es Teh Manis')->assertDontSee('Nasi Ayam Bakar')
            ->set('filterCategory', null)
            ->call('toggle', $nasi->id);

        $this->assertFalse($nasi->fresh()->is_available);
        $component->assertSee('HABIS');
    }
}
