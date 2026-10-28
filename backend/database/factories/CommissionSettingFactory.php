<?php

namespace Database\Factories;

use App\Models\CommissionSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionSetting>
 */
class CommissionSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'rate' => fake()->randomFloat(2, 0, 30),
            'type' => 'PERCENTAGE',
            'is_active' => true,
        ];
    }
}
