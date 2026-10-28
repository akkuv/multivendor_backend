<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WishlistItem>
 */
class WishlistItemFactory extends Factory
{
    protected $model = WishlistItem::class;

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
            'price_at_add' => 100.00,
            'notify_price_drops' => false,
            'notify_back_in_stock' => false,
            'price_drop_notified' => false,
            'back_in_stock_notified' => false,
        ];
    }

    public function notifyPriceDrops(): static
    {
        return $this->state(fn (array $attributes) => ['notify_price_drops' => true]);
    }

    public function notifyBackInStock(): static
    {
        return $this->state(fn (array $attributes) => ['notify_back_in_stock' => true]);
    }
}
