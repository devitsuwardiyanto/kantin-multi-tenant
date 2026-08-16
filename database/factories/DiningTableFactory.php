<?php

namespace Database\Factories;

use App\Models\Canteen;
use App\Models\DiningTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DiningTable> */
class DiningTableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'canteen_id' => Canteen::factory(),
            'code' => 'T'.fake()->unique()->numberBetween(1, 9999),
            'label' => 'Meja '.fake()->numberBetween(1, 50),
            'zone' => fake()->randomElement(['Indoor', 'Outdoor', null]),
            'status' => 'active',
        ];
    }
}
