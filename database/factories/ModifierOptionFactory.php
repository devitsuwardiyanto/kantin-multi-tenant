<?php

namespace Database\Factories;

use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ModifierOption> */
class ModifierOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'group_id' => fn (array $attributes) => ModifierGroup::factory()
                ->create(['tenant_id' => $attributes['tenant_id']])->id,
            'name' => fake()->word(),
            'price_delta' => fake()->numberBetween(0, 10) * 1000,
            'stock_qty' => 50,
            'is_available' => true,
        ];
    }
}
