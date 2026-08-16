<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Constraint diuji pada MariaDB nyata (bukan SQLite memory) — komposit FK lintas-tenant,
 * idempotency, dan invariant order induk tanpa tenant_id.
 */
class DatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_cannot_reference_another_tenants_category(): void
    {
        $canteen = Canteen::factory()->create();
        $a = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $b = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $categoryB = MenuCategory::factory()->create(['tenant_id' => $b->id]);

        $this->expectException(QueryException::class);
        Menu::factory()->create([
            'tenant_id' => $a->id,             // tenant A
            'category_id' => $categoryB->id,   // kategori tenant B -> composite FK gagal
        ]);
    }

    public function test_modifier_option_cannot_reference_another_tenants_group(): void
    {
        $canteen = Canteen::factory()->create();
        $a = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $b = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $groupB = ModifierGroup::factory()->create(['tenant_id' => $b->id]);

        $this->expectException(QueryException::class);
        ModifierOption::factory()->create([
            'tenant_id' => $a->id,
            'group_id' => $groupB->id,
        ]);
    }

    public function test_duplicate_tenant_code_in_same_canteen_is_rejected(): void
    {
        $canteen = Canteen::factory()->create();
        Tenant::factory()->create(['canteen_id' => $canteen->id, 'code' => 'DUP']);

        $this->expectException(QueryException::class);
        Tenant::factory()->create(['canteen_id' => $canteen->id, 'code' => 'DUP']);
    }

    public function test_stock_movement_idempotency_key_is_unique(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id]);

        $row = fn () => [
            'tenant_id' => $tenant->id, 'menu_id' => $menu->id,
            'idempotency_key' => 'stk-key-1', 'type' => 'sale', 'quantity_delta' => -1,
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('menu_stock_movements')->insert($row());

        $this->expectException(QueryException::class);
        DB::table('menu_stock_movements')->insert($row());
    }

    public function test_orders_has_no_tenant_id_and_supports_multiple_tenant_orders(): void
    {
        $this->assertFalse(Schema::hasColumn('orders', 'tenant_id'), 'orders TIDAK boleh punya tenant_id');

        $canteen = Canteen::factory()->create();
        $a = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $b = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $ca = CommissionScheme::factory()->create(['tenant_id' => $a->id]);
        $cb = CommissionScheme::factory()->create(['tenant_id' => $b->id]);

        $orderId = DB::table('orders')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'order_number' => 'ORD-1', 'canteen_id' => $canteen->id,
            'checkout_key' => 'ck-1', 'tracking_token_hash' => random_bytes(32),
            'status' => 'pending', 'grand_total_amount' => 50000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([[$a, $ca], [$b, $cb]] as [$t, $c]) {
            DB::table('tenant_orders')->insert([
                'order_id' => $orderId, 'tenant_id' => $t->id, 'commission_id' => $c->id,
                'status' => 'pending', 'commission_rate_snapshot' => 0.1500,
                'subtotal_amount' => 25000, 'commission_amount' => 3750, 'net_amount' => 21250,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Satu order induk memuat dua tenant_orders (lintas tenant) tanpa melanggar scope.
        $this->assertSame(2, DB::table('tenant_orders')->where('order_id', $orderId)->count());
    }

    public function test_money_columns_cast_to_integer(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => 15000]);

        $this->assertIsInt($menu->refresh()->base_price);
        $this->assertSame(15000, $menu->base_price);
    }
}
