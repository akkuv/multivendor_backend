<?php

namespace Database\Factories;

use App\Models\Color;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Color>
 */
class ColorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Black',
            'White',
            'Red',
            'Blue',
            'Green',
            'Yellow',
            'Grey',
            'Navy',
            'Beige',
            'Purple',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'hex_code' => fake()->hexColor(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
