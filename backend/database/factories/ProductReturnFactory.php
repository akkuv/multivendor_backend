<?php

namespace Database\Factories;

use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductReturn>
 */
class ProductReturnFactory extends Factory
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
            'order_item_id' => OrderItem::factory(),
            'user_id' => User::factory(),
            'quantity' => 1,
            'reason' => fake()->randomElement(['Wrong item', 'Damaged', 'Not as described', 'Sizing issue']),
            'description' => fake()->sentence(),
            'images' => [],
            'status' => ReturnStatus::Requested->value,
            'status_notes' => null,
            'pickup_address_id' => null,
            'pickup_scheduled_at' => null,
            'picked_up_at' => null,
            'received_at' => null,
            'inspecting_at' => null,
            'approved_for_refund_at' => null,
            'refunded_at' => null,
            'closed_at' => null,
        ];
    }
}
