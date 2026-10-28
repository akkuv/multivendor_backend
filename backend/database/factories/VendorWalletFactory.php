<?php

namespace Database\Factories;

use App\Enums\VendorWalletStatus;
use App\Models\VendorProfile;
use App\Models\VendorWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorWallet>
 */
class VendorWalletFactory extends Factory
{
    protected $model = VendorWallet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => VendorProfile::factory(),
            'currency' => 'INR',
            'available_balance' => 0,
            'pending_balance' => 0,
            'held_balance' => 0,
            'paid_total' => 0,
            'status' => VendorWalletStatus::Active->value,
        ];
    }
}
