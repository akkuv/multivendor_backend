<?php

namespace Database\Factories;

use App\Models\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Collection>
 */
class CollectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'image' => fake()->optional()->imageUrl(),
            'banner_image' => null,
            'gender' => fake()->randomElement(['unisex', 'male', 'female', 'kids']),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 20),
            'meta_title' => fake()->optional()->words(4, true),
            'meta_description' => fake()->optional()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
