<?php

namespace Database\Factories;

use App\Models\ModifierGroup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ModifierGroup> */
class ModifierGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->word(),
            'min_select' => 0,
            'max_select' => 1,
            'is_active' => true,
        ];
    }
}
