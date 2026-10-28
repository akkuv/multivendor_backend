<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WishlistNotification>
 */
class WishlistNotificationFactory extends Factory
{
    protected $model = WishlistNotification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wishlist_item_id' => WishlistItem::factory(),
            'product_id' => Product::factory(),
            'type' => WishlistNotification::priceDrop(),
            'old_price' => 100.00,
            'new_price' => 80.00,
            'is_read' => false,
        ];
    }
}
