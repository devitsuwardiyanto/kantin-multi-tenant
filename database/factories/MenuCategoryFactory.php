<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MenuCategory> */
class MenuCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->word(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
