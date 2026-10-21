<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Tenant;
use App\Modules\Ordering\Exceptions\CartException;
use App\Modules\Ordering\Services\CartService;
use App\Support\Tokens\OpaqueToken;
use Database\Seeders\DemoCanteenSeeder;
use Database\Seeders\EsKopiModifierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

/**
 * Seeder demo Bab 7: Es Kopi dengan empat grup modifier (1–1, 1–1, 0–1, 0–2) dan Contoh 7.2
 * (Rp15.000 + Dingin + L + Boba = Rp23.000) yang dihitung oleh CartService.
 */
class EsKopiModifierSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_menolak_berjalan_tanpa_tenant_kopi(): void
    {
        $this->expectException(RuntimeException::class);

        $this->seed(EsKopiModifierSeeder::class);
    }

    public function test_seeder_membuat_es_kopi_dengan_empat_grup_dan_idempoten(): void
    {
        $this->seed([DemoCanteenSeeder::class, EsKopiModifierSeeder::class, EsKopiModifierSeeder::class]);

        $tenant = Tenant::query()->where('code', 'KOPI')->firstOrFail();
        $menu = Menu::query()->where('tenant_id', $tenant->id)->where('name', 'Es Kopi')->firstOrFail();

        $this->assertSame(15000, (int) $menu->base_price);
        $this->assertSame(1, Menu::query()->where('tenant_id', $tenant->id)->where('name', 'Es Kopi')->count());

        $groups = $menu->modifierGroups()->orderBy('menu_modifier_groups.sort_order')->get();
        $this->assertSame(['Suhu', 'Ukuran Gelas', 'Tingkat Gula', 'Topping'], $groups->pluck('name')->all());
        $this->assertSame([[1, 1], [1, 1], [0, 1], [0, 2]], $groups->map(fn (ModifierGroup $g): array => [$g->min_select, $g->max_select])->all());

        // Grup Topping dipakai ulang: tetap satu grup, kini juga berisi Boba.
        $this->assertSame(1, ModifierGroup::query()->where('tenant_id', $tenant->id)->where('name', 'Topping')->count());
        $this->assertTrue(ModifierOption::query()->where('tenant_id', $tenant->id)->where('name', 'Boba')->exists());
    }

    public function test_contoh_7_2_harga_akhir_dua_puluh_tiga_ribu(): void
    {
        $this->seed([DemoCanteenSeeder::class, EsKopiModifierSeeder::class]);

        $canteen = Canteen::query()->where('code', 'KTN-PUSAT')->firstOrFail();
        $tenant = Tenant::query()->where('code', 'KOPI')->firstOrFail();
        $menu = Menu::query()->where('tenant_id', $tenant->id)->where('name', 'Es Kopi')->firstOrFail();
        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();
        $id = fn (string $name): int => (int) ModifierOption::query()->where('tenant_id', $tenant->id)->where('name', $name)->value('id');

        $cart = app(CartService::class);

        try {
            $cart->add($session, $menu->id, 1, [$id('Dingin'), $id('L (450 ml)'), $id('Boba')]);
            $this->assertSame(23000, $cart->view($session)->subtotal);

            $this->expectException(CartException::class);
            $cart->add($session, $menu->id, 1, [$id('L (450 ml)')]); // Suhu wajib dipilih
        } finally {
            Redis::del('cart:'.$session->id);
        }
    }
}
