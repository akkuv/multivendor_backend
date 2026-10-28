<?php

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Color',
            'Size',
            'RAM',
            'Storage',
            'Material',
            'Weight',
            'Capacity',
            'Connectivity',
            'Warranty',
            'Screen Size',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => fake()->randomElement(['SELECT', 'RADIO', 'BUTTON']),
            'description' => fake()->optional()->sentence(),
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
