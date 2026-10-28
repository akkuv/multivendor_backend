<?php

namespace Database\Factories;

use App\Enums\CommissionTransactionType;
use App\Models\CommissionTransaction;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionTransaction>
 */
class CommissionTransactionFactory extends Factory
{
    protected $model = CommissionTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => VendorProfile::factory(),
            'rate' => 10,
            'amount' => fake()->randomFloat(2, 10, 5000),
            'type' => CommissionTransactionType::Charge->value,
            'description' => fake()->sentence(),
        ];
    }
}
