<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\RecentlyViewed;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecentlyViewed>
 */
class RecentlyViewedFactory extends Factory
{
    protected $model = RecentlyViewed::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'view_count' => 1,
            'last_viewed_at' => now(),
        ];
    }
}
