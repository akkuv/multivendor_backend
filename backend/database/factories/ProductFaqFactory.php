<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductFaq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductFaq>
 */
class ProductFaqFactory extends Factory
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
            'question' => fake()->sentence().'?',
            'answer' => fake()->paragraph(),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
