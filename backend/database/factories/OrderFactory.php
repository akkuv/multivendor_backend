<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 500, 20000);
        $discount = 0;
        $shippingCharge = 50.00;
        $tax = round($subtotal * 0.18, 2);
        $total = round($subtotal - $discount + $shippingCharge + $tax, 2);

        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-'.strtoupper(Str::random(10)),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'shipping_charge' => $shippingCharge,
            'total' => $total,
            'order_status' => OrderStatus::Pending->value,
            'payment_status' => PaymentStatus::Pending->value,
            'shipping_address_id' => null,
            'coupon_id' => null,
            'razorpay_order_id' => null,
            'razorpay_payment_id' => null,
            'payment_method' => null,
            'paid_at' => null,
            'cancelled_at' => null,
            'notes' => null,
        ];
    }
}
