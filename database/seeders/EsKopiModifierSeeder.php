<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data demo Bab 7 (Buku Ajar): menu "Es Kopi" pada tenant KOPI dengan empat grup modifier yang
 * mewakili aturan min_select/max_select pada Tabel 7.3 dan Contoh 7.2 (Rp15.000 + Dingin Rp0 +
 * L Rp3.000 + Boba Rp5.000 = Rp23.000).
 *
 *  - Suhu         1–1  wajib tunggal   (radio)
 *  - Ukuran Gelas 1–1  wajib tunggal   (radio)
 *  - Tingkat Gula 0–1  opsional tunggal (tidak memilih = gula normal)
 *  - Topping      0–2  multi-pilih opsional (grup milik DemoCanteenSeeder, DIPAKAI ULANG)
 *
 * Operator demo (tenant@kantin.test) juga diberi peran operator pada tenant KOPI agar layar dapur
 * (KDS) pesanan Es Kopi dapat dicoba. Idempoten (firstOrNew + forceFill) dan bergantung pada
 * DemoCanteenSeeder (tenant KOPI).
 */
class EsKopiModifierSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'KOPI')->first();
        if ($tenant === null) {
            throw new RuntimeException('Tenant KOPI belum ada. Jalankan DemoCanteenSeeder terlebih dahulu.');
        }

        $category = tap(MenuCategory::firstOrNew(['tenant_id' => $tenant->id, 'name' => 'Minuman']))
            ->forceFill(['sort_order' => 2, 'is_active' => true]);
        $category->save();

        $menu = Menu::firstOrNew(['tenant_id' => $tenant->id, 'name' => 'Es Kopi'])
            ->forceFill([
                'category_id' => $category->id,
                'description' => 'Espresso, susu segar, dan es batu. Racik sesuai selera.',
                'base_price' => 15000,
                'stock_qty' => 20,
                'is_available' => true,
                'prep_minutes' => 5,
            ]);
        $menu->save();

        $blueprint = [
            ['Suhu', 1, 1, [['Dingin', 0], ['Panas', 0]]],
            ['Ukuran Gelas', 1, 1, [['S (250 ml)', 0], ['M (350 ml)', 1500], ['L (450 ml)', 3000]]],
            ['Tingkat Gula', 0, 1, [['Kurangi gula', 0], ['Tanpa gula', 0]]],
            ['Topping', 0, 2, [['Boba', 5000]]],
        ];

        $pivot = [];
        foreach ($blueprint as $order => [$name, $min, $max, $options]) {
            $group = tap(ModifierGroup::firstOrNew(['tenant_id' => $tenant->id, 'name' => $name]))
                ->forceFill(['min_select' => $min, 'max_select' => $max, 'is_active' => true]);
            $group->save();

            foreach ($options as [$optionName, $delta]) {
                tap(ModifierOption::firstOrNew(['tenant_id' => $tenant->id, 'group_id' => $group->id, 'name' => $optionName]))
                    ->forceFill(['price_delta' => $delta, 'stock_qty' => 100, 'is_available' => true])->save();
            }

            $pivot[$group->id] = ['tenant_id' => $tenant->id, 'sort_order' => $order + 1];
        }

        $menu->modifierGroups()->syncWithoutDetaching($pivot);

        $operator = User::where('email', 'tenant@kantin.test')->first();
        if ($operator !== null) {
            UserTenantRole::firstOrCreate(
                ['user_id' => $operator->id, 'tenant_id' => $tenant->id, 'role' => 'operator'],
            );
        }
    }
}
