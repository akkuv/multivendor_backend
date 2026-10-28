<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductFee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductFee>
 */
class ProductFeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement(['Handling charge', 'Packaging fee']),
            'amount' => fake()->randomFloat(2, 5, 150),
        ];
    }
}
