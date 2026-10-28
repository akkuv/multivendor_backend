<?php

namespace Database\Factories;

use App\Models\Size;
use App\Models\SizeGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Size>
 */
class SizeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $value = fake()->unique()->randomElement([
            'S', 'M', 'L', 'XL', 'XXL', '6', '7', '8', '9', '10', '11',
        ]);

        return [
            'size_group_id' => SizeGroup::factory(),
            'name' => $value,
            'slug' => Str::slug($value),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function forGroup(int $sizeGroupId): static
    {
        return $this->state(fn (array $attributes) => [
            'size_group_id' => $sizeGroupId,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
