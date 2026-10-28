<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\VendorOrder;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VendorOrder>
 */
class VendorOrderFactory extends Factory
{
    protected $model = VendorOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 10000);
        $discount = 0;
        $shippingCharge = 50.00;
        $tax = round($subtotal * 0.18, 2);
        $total = round($subtotal - $discount + $shippingCharge + $tax, 2);

        return [
            'order_id' => Order::factory(),
            'vendor_id' => VendorProfile::factory(),
            'vendor_order_number' => 'SUB-'.strtoupper(Str::random(10)),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'shipping_charge' => $shippingCharge,
            'total' => $total,
            'status' => OrderStatus::Pending->value,
            'shipped_at' => null,
            'delivered_at' => null,
        ];
    }
}
