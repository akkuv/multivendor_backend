<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
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
            'order_item_id' => null,
            'user_id' => User::factory(),
            'product_return_id' => null,
            'amount' => fake()->randomFloat(2, 50, 5000),
            'quantity' => 1,
            'status' => RefundStatus::Pending->value,
            'method' => null,
            'wallet_amount' => 0,
            'gateway_amount' => 0,
            'reason' => fake()->sentence(),
            'razorpay_refund_id' => null,
            'processed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefundStatus::Completed->value,
            'processed_at' => now(),
            'razorpay_refund_id' => 'rfnd_'.fake()->uuid(),
        ]);
    }
}
