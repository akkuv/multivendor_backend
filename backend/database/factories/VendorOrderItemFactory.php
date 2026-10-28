<?php

namespace Database\Factories;

use App\Enums\VendorOrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\VendorOrder;
use App\Models\VendorOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorOrderItem>
 */
class VendorOrderItemFactory extends Factory
{
    protected $model = VendorOrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create();
        $unitPrice = fake()->randomFloat(2, 50, 5000);
        $quantity = fake()->numberBetween(1, 5);

        return [
            'vendor_order_id' => VendorOrder::factory(),
            'order_item_id' => OrderItem::factory(),
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_image' => null,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'total' => round($unitPrice * $quantity, 2),
            'commission_rate' => 10,
            'commission_amount' => round($unitPrice * $quantity * 0.10, 2),
            'status' => VendorOrderStatus::Pending->value,
        ];
    }
}
