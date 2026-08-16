<?php

namespace Database\Factories;

use App\Models\Canteen;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Canteen> */
class CanteenFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'code' => strtoupper(Str::random(6)),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'name' => $name,
            'tax_rate' => 0.1000,
            'service_fee_rate' => 0.0200,
            'status' => 'active',
        ];
    }
}
