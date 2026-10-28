<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => null,
            'code' => strtoupper(Str::random(8)),
            'type' => 'PERCENTAGE',
            'value' => fake()->randomFloat(2, 5, 50),
            'min_amount' => fake()->randomFloat(2, 500, 2000),
            'max_discount' => fake()->randomFloat(2, 100, 500),
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'usage_limit' => 100,
            'per_user_limit' => 1,
            'first_order_only' => false,
            'used_count' => 0,
            'is_active' => true,
        ];
    }

    public function fixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'FIXED',
            'value' => fake()->randomFloat(2, 50, 1000),
            'max_discount' => null,
        ]);
    }
}
