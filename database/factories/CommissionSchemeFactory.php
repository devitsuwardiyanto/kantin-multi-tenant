<?php

namespace Database\Factories;

use App\Models\CommissionScheme;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommissionScheme> */
class CommissionSchemeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'commission_rate' => 0.1500,
            'valid_from' => now()->subMonth(),
            'valid_to' => null,
        ];
    }
}
