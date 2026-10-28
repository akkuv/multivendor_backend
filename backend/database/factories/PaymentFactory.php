<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 500, 20000),
            'method' => 'razorpay',
            'status' => PaymentStatus::Pending->value,
            'gateway' => 'razorpay',
            'transaction_id' => fake()->uuid(),
            'payload' => [],
        ];
    }
}
