<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'vendor_id' => VendorProfile::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(3, true),
            'product_image' => null,
            'unit_price' => fake()->randomFloat(2, 100, 5000),
            'quantity' => fake()->numberBetween(1, 3),
            'total' => 0,
            'commission_rate' => 10.00,
            'commission_amount' => 0,
            'status' => OrderStatus::Pending->value,
        ];
    }
}
