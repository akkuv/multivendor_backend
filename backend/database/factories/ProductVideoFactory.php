<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVideo>
 */
class ProductVideoFactory extends Factory
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
            'url' => fake()->url(),
            'provider' => fake()->randomElement(['upload', 'youtube', 'vimeo']),
            'thumbnail_url' => fake()->optional()->imageUrl(),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
