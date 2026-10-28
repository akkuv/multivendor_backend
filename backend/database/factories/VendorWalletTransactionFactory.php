<?php

namespace Database\Factories;

use App\Enums\VendorWalletTransactionStatus;
use App\Enums\VendorWalletTransactionType;
use App\Models\VendorWallet;
use App\Models\VendorWalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorWalletTransaction>
 */
class VendorWalletTransactionFactory extends Factory
{
    protected $model = VendorWalletTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => VendorWallet::factory(),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'balance_before' => 0,
            'balance_after' => 0,
            'type' => VendorWalletTransactionType::Adjustment->value,
            'status' => VendorWalletTransactionStatus::Completed->value,
            'description' => fake()->sentence(),
        ];
    }
}
