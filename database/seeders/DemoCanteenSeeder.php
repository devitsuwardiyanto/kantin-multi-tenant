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
            ],
        ];

        $tenants = [];
        foreach ($blueprint as $code => $spec) {
            $tenant = tap(Tenant::withTrashed()->firstOrNew(['canteen_id' => $canteen->id, 'code' => $code]))
                ->forceFill([
                    'slug' => strtolower($code).'-pusat',
                    'display_name' => $spec['display'],
                    'status' => 'active',
                    'deleted_at' => null,
                ]);
            $tenant->save();
            $tenants[$code] = $tenant;

            tap(TenantBalance::firstOrNew(['tenant_id' => $tenant->id]))
                ->forceFill(['available_amount' => 0, 'held_amount' => 0])->save();

            tap(CommissionScheme::firstOrNew(['tenant_id' => $tenant->id, 'valid_from' => now()->startOfYear()]))
                ->forceFill(['commission_rate' => 0.1500, 'valid_to' => null])->save();

            $category = tap(MenuCategory::firstOrNew(['tenant_id' => $tenant->id, 'name' => $spec['category']]))
                ->forceFill(['sort_order' => 1, 'is_active' => true]);
            $category->save();

            foreach ($spec['menus'] as $i => [$name, $price]) {
                tap(Menu::firstOrNew(['tenant_id' => $tenant->id, 'name' => $name]))
                    ->forceFill([
                        'category_id' => $category->id,
                        'base_price' => $price,
                        'stock_qty' => 100,
                        'is_available' => true,
                        'prep_minutes' => 10 + $i,
                    ])->save();
            }

            [$groupName, $options] = $spec['modifier'];
            $group = tap(ModifierGroup::firstOrNew(['tenant_id' => $tenant->id, 'name' => $groupName]))
                ->forceFill(['min_select' => 1, 'max_select' => 1, 'is_active' => true]);
            $group->save();

            foreach ($options as [$optName, $delta]) {
                tap(ModifierOption::firstOrNew(['tenant_id' => $tenant->id, 'group_id' => $group->id, 'name' => $optName]))
                    ->forceFill(['price_delta' => $delta, 'stock_qty' => 100, 'is_available' => true])->save();
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
