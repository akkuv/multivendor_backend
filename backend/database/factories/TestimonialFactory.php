<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_name' => fake()->name(),
            'author_role' => fake()->optional()->jobTitle(),
            'author_avatar' => null,
            'image' => null,
            'url' => null,
            'content' => fake()->paragraph(),
            'rating' => fake()->numberBetween(4, 5),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
