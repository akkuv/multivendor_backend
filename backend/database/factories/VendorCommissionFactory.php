<?php

namespace Database\Factories;

use App\Enums\VendorCommissionStatus;
use App\Models\VendorCommission;
use App\Models\VendorOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorCommission>
 */
class VendorCommissionFactory extends Factory
{
    protected $model = VendorCommission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $vendorOrder = VendorOrder::factory()->create();

        return [
            'vendor_id' => $vendorOrder->vendor_id,
            'order_id' => $vendorOrder->order_id,
            'vendor_order_id' => $vendorOrder->id,
            'eligible_amount' => 0,
            'discount_amount' => 0,
            'commission_rate' => 10,
            'commission_amount' => 0,
            'net_payable' => 0,
            'status' => VendorCommissionStatus::Settled->value,
        ];
    }

    /** @return Factory<VendorCommission> */
    public function settled(float $eligibleAmount = 1000, float $rate = 10): Factory
    {
        $commission = round($eligibleAmount * ($rate / 100), 2);

        return $this->state(fn (): array => [
            'eligible_amount' => $eligibleAmount,
            'commission_rate' => $rate,
            'commission_amount' => $commission,
            'net_payable' => round($eligibleAmount - $commission, 2),
            'status' => VendorCommissionStatus::Settled->value,
            'settled_at' => now(),
        ]);
    }
}
