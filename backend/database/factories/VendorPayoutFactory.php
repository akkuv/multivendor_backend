<?php

namespace Database\Factories;

use App\Enums\VendorPayoutStatus;
use App\Models\VendorPayout;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorPayout>
 */
class VendorPayoutFactory extends Factory
{
    protected $model = VendorPayout::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => VendorProfile::factory(),
            'amount' => fake()->randomFloat(2, 100, 50000),
            'method' => 'bank_transfer',
            'status' => VendorPayoutStatus::Pending->value,
            'requested_at' => now(),
        ];
    }
}
