<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Menu> */
class MenuFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            // Kategori dibuat untuk tenant yang sama (composite FK) — closure menerima atribut terselesaikan.
            'category_id' => fn (array $attributes) => MenuCategory::factory()
                ->create(['tenant_id' => $attributes['tenant_id']])->id,
            'name' => fake()->unique()->word(),
            'base_price' => fake()->numberBetween(8, 60) * 1000,
            'stock_qty' => 50,
            'is_available' => true,
            'prep_minutes' => fake()->numberBetween(5, 20),
        ];
    }
}
