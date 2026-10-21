<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Tenant;
use Database\Seeders\DemoCanteenSeeder;
use Database\Seeders\EsKopiModifierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Seeder demo Bab 7: Es Kopi dengan empat grup modifier (1–1, 1–1, 0–1, 0–2) dan Contoh 7.2
 * (Rp15.000 + Dingin + L + Boba = Rp23.000). Pada Pertemuan 7 harga dihitung dari data modifier;
 * mulai Pertemuan 8 contoh yang sama dihitung CartService.
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

        $tenant = Tenant::query()->where('code', 'KOPI')->firstOrFail();
        $menu = Menu::query()->where('tenant_id', $tenant->id)->where('name', 'Es Kopi')->firstOrFail();
        $delta = fn (string $name): int => (int) ModifierOption::query()->where('tenant_id', $tenant->id)->where('name', $name)->value('price_delta');

        // Harga akhir = harga dasar + selisih harga opsi terpilih. Validasi pilihan wajib (min/maks)
        // dan perhitungan di keranjang dikerjakan CartService pada Pertemuan 8 (UC-04).
        $this->assertSame(23000, (int) $menu->base_price + $delta('Dingin') + $delta('L (450 ml)') + $delta('Boba'));
    }
}
