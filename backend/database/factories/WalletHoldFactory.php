<?php

namespace Database\Factories;

use App\Enums\WalletHoldStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletHold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletHold>
 */
class WalletHoldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'status' => WalletHoldStatus::Pending->value,
            'reference_type' => Order::class,
            'reference_id' => Order::factory(),
            'description' => fake()->sentence(),
            'expires_at' => now()->addHours(24),
            'captured_at' => null,
            'released_at' => null,
        ];
    }
}
