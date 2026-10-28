<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorReview>
 */
class VendorReviewFactory extends Factory
{
    protected $model = VendorReview::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => VendorProfile::factory(),
            'user_id' => User::factory(),
            'order_id' => null,
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'is_verified' => true,
            'is_approved' => true,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['is_approved' => false]);
    }
}
