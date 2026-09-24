<?php

namespace Database\Seeders;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Tenant;
use App\Models\TenantBalance;
use App\Models\TenantOperatingHour;
use App\Models\User;
use App\Models\UserCanteenRole;
use App\Models\UserTenantRole;
use Illuminate\Database\Seeder;

/**
 * Data demo deterministik & idempoten: 1 kantin, 2 tenant, komisi, katalog, meja, role.
 * Idempoten via firstOrNew + forceFill (kolom guarded seperti tenant_id di-set eksplisit).
 */
class DemoCanteenSeeder extends Seeder
{
    public function run(): void
    {
        $canteen = tap(Canteen::firstOrNew(['code' => 'KTN-PUSAT']))
            ->forceFill([
                'slug' => 'kantin-pusat',
                'name' => 'Kantin Pusat',
                'tax_rate' => 0.1000,
                'service_fee_rate' => 0.0200,
                'status' => 'active',
            ]);
        $canteen->save();

        // Meja demo
        foreach ([['M01', 'Meja 1', 'Indoor'], ['M02', 'Meja 2', 'Indoor'], ['M03', 'Meja 3', 'Outdoor']] as [$code, $label, $zone]) {
            tap(DiningTable::firstOrNew(['canteen_id' => $canteen->id, 'code' => $code]))
                ->forceFill(['label' => $label, 'zone' => $zone, 'status' => 'active'])->save();
        }

        $blueprint = [
            'AYAM' => [
                'display' => 'Ayam Geprek Mantul',
                'category' => 'Paket Ayam',
                'menus' => [['Geprek Original', 15000], ['Geprek Keju', 20000]],
                'modifier' => ['Level Pedas', [['Level 1', 0], ['Level 5', 2000]]],
            ],
            'KOPI' => [
                'display' => 'Kopi Kita',
                'category' => 'Kopi Susu',
                'menus' => [['Kopi Susu Gula Aren', 18000], ['Americano', 16000]],
                'modifier' => ['Ukuran', [['Regular', 0], ['Large', 5000]]],
                // UC-04: grup opsional (maks 2) dengan satu opsi habis agar alur 2a dapat dicoba.
                'extra' => ['Topping', 0, 2, [['Extra shot espresso', 3000, true], ['Cream cheese foam', 4000, false]]],
            ],
        ];

        $tenants = [];
        foreach ($blueprint as $code => $spec) {
            $tenant = tap(Tenant::withTrashed()->firstOrNew(['canteen_id' => $canteen->id, 'code' => $code]))
                ->forceFill([
                    'slug' => strtolower($code).'-pusat',
                    'display_name' => $spec['display'],
                    'status' => 'active',
                    'pre_order_enabled' => true, // UC-06: demo pre-order aktif
                    'deleted_at' => null,
                ]);
            $tenant->save();
            $tenants[$code] = $tenant;

            // UC-02: jam operasional demo (WIB). AYAM buka siang–malam; KOPI buka 24 jam agar demo
            // pemindaian QR selalu dapat dicoba.
            [$opens, $closes] = $code === 'AYAM' ? ['10:00:00', '21:00:00'] : ['00:00:00', '23:59:00'];
            foreach (range(0, 6) as $day) {
                TenantOperatingHour::withoutGlobalScope('tenant')->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'day_of_week' => $day],
                    ['opens_at' => $opens, 'closes_at' => $closes],
                );
            }

            tap(TenantBalance::firstOrNew(['tenant_id' => $tenant->id]))
                ->forceFill(['available_amount' => 0, 'held_amount' => 0])->save();

            tap(CommissionScheme::firstOrNew(['tenant_id' => $tenant->id, 'valid_from' => now()->startOfYear()]))
                ->forceFill(['commission_rate' => 0.1500, 'valid_to' => null])->save();

            $category = tap(MenuCategory::firstOrNew(['tenant_id' => $tenant->id, 'name' => $spec['category']]))
                ->forceFill(['sort_order' => 1, 'is_active' => true]);
            $category->save();

            $menus = [];
            foreach ($spec['menus'] as $i => [$name, $price]) {
                $menu = Menu::firstOrNew(['tenant_id' => $tenant->id, 'name' => $name])
                    ->forceFill([
                        'category_id' => $category->id,
                        'base_price' => $price,
                        'stock_qty' => 100,
                        'is_available' => true,
                        'prep_minutes' => 10 + $i,
                    ]);
                $menu->save();
                $menus[] = $menu;
            }

            [$groupName, $options] = $spec['modifier'];
            $group = tap(ModifierGroup::firstOrNew(['tenant_id' => $tenant->id, 'name' => $groupName]))
                ->forceFill(['min_select' => 1, 'max_select' => 1, 'is_active' => true]);
            $group->save();

            foreach ($options as [$optName, $delta]) {
                tap(ModifierOption::firstOrNew(['tenant_id' => $tenant->id, 'group_id' => $group->id, 'name' => $optName]))
                    ->forceFill(['price_delta' => $delta, 'stock_qty' => 100, 'is_available' => true])->save();
            }
            $groups = [$group];

            if (isset($spec['extra'])) {
                [$extraName, $min, $max, $extraOptions] = $spec['extra'];
                $extra = tap(ModifierGroup::firstOrNew(['tenant_id' => $tenant->id, 'name' => $extraName]))
                    ->forceFill(['min_select' => $min, 'max_select' => $max, 'is_active' => true]);
                $extra->save();
                foreach ($extraOptions as [$optName, $delta, $available]) {
                    tap(ModifierOption::firstOrNew(['tenant_id' => $tenant->id, 'group_id' => $extra->id, 'name' => $optName]))
                        ->forceFill(['price_delta' => $delta, 'stock_qty' => 100, 'is_available' => $available])->save();
                }
                $groups[] = $extra;
            }

            // UC-04: grup modifier dipasang pada menu pertama tenant; menu kedua tanpa modifier
            // sehingga alur tambah langsung (UC-03) dan formulir kustomisasi (UC-04) sama-sama dapat dicoba.
            foreach ($groups as $order => $modifierGroup) {
                $menus[0]->modifierGroups()->syncWithoutDetaching([
                    $modifierGroup->id => ['tenant_id' => $tenant->id, 'sort_order' => $order + 1],
                ]);
            }
        }

        // Operator demo -> tenant AYAM (peran operator).
        $operator = User::where('email', 'tenant@kantin.test')->first();
        if ($operator !== null) {
            UserTenantRole::firstOrCreate(
                ['user_id' => $operator->id, 'tenant_id' => $tenants['AYAM']->id, 'role' => 'operator'],
            );
        }

        // Admin demo -> pengelola (manager) Kantin Pusat.
        $admin = User::where('email', 'admin@kantin.test')->first();
        if ($admin !== null) {
            UserCanteenRole::firstOrCreate(
                ['user_id' => $admin->id, 'canteen_id' => $canteen->id, 'role' => 'manager'],
            );
        }
    }
}
