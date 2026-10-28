<?php

namespace Database\Factories;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
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
            'type' => WalletTransactionType::Credit->value,
            'amount' => fake()->randomFloat(2, 10, 5000),
            'balance_before' => 0,
            'balance_after' => fake()->randomFloat(2, 0, 10000),
            'status' => WalletTransactionStatus::Completed->value,
            'description' => fake()->sentence(),
            'reference_type' => null,
            'reference_id' => null,
        ];
    }
}
