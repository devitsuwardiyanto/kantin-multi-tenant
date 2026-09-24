<?php

namespace Database\Factories;

use App\Models\Canteen;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word();

        return [
            'canteen_id' => Canteen::factory(),
            'code' => strtoupper(Str::random(5)),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'display_name' => Str::title($name),
            'status' => 'active',
        ];
    }

    /** UC-06: tenant menerima pre-order dengan kapasitas slot tertentu. */
    public function preOrder(int $slotCapacity = 5): static
    {
        return $this->state(['pre_order_enabled' => true, 'pre_order_slot_capacity' => $slotCapacity]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }
}
