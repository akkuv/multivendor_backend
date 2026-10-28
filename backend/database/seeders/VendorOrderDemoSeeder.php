<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Models\VendorOrder;
use App\Models\VendorOrderItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds vendor orders across the fulfilment workflow so the vendor panel has a
 * full, demonstrable order pipeline. Skips when vendor orders already exist.
 */
class VendorOrderDemoSeeder extends Seeder
{
    /**
     * @var array<int, array{status: string, rank: int}>
     */
    private const STATUSES = [
        ['status' => 'PENDING', 'rank' => 1],
        ['status' => 'CONFIRMED', 'rank' => 2],
        ['status' => 'PROCESSING', 'rank' => 3],
        ['status' => 'PACKED', 'rank' => 4],
        ['status' => 'READY_TO_SHIP', 'rank' => 5],
        ['status' => 'SHIPPED', 'rank' => 6],
        ['status' => 'OUT_FOR_DELIVERY', 'rank' => 7],
        ['status' => 'DELIVERED', 'rank' => 8],
        ['status' => 'RETURN_REQUESTED', 'rank' => 9],
        ['status' => 'CANCELLED', 'rank' => -1],
    ];

    /**
     * @var array<int, string>
     */
    private const COURIERS = ['Delhivery', 'Blue Dart', 'Ecom Express', 'DTDC'];

    public function run(): void
    {
        if (VendorOrder::query()->exists()) {
            $this->command?->info('Vendor orders already seeded. Skipping.');

            return;
        }

        $customers = User::where('role', 'CUSTOMER')->get();
        $products = Product::whereNotNull('vendor_id')->get(['id', 'vendor_id', 'name', 'price', 'images']);

        if ($customers->isEmpty() || $products->isEmpty()) {
            $this->command?->warn('Need customers and products to seed vendor orders.');

            return;
        }

        $now = now();
        $created = 0;

        foreach (self::STATUSES as $index => $row) {
            $customer = $customers->get($index % $customers->count());
            $createdAt = $now->copy()->subDays(40 - $index * 3)->setTime(10, 30);

            $order = $this->placedOrder($customer, $createdAt);

            $distinctVendors = $products->filter(fn ($p) => $p->vendor_id !== null)->unique('vendor_id')->shuffle()->take(2);

            foreach ($distinctVendors as $product) {
                $vendorOrder = $this->buildVendorOrder($order, $product, $row['status'], $row['rank'], $createdAt);

                if ($row['rank'] >= 6) {
                    $this->buildShipment($vendorOrder, $order, $row['rank'], $createdAt);
                }

                $created++;
            }
        }

        $this->command?->info("Seeded {$created} vendor orders across the fulfilment workflow.");
    }

    protected function placedOrder(User $customer, \DateTimeInterface $createdAt): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-'.strtoupper(Str::random(6)),
            'idempotency_key' => (string) Str::uuid(),
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'shipping_charge' => 0,
            'cod_charge' => 0,
            'wallet_amount' => 0,
            'total' => 0,
            'order_status' => OrderStatus::Pending->value,
            'payment_status' => PaymentStatus::Paid->value,
            'payment_method' => 'razorpay',
            'razorpay_payment_id' => 'pay_demo_'.Str::random(8),
            'paid_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    /**
     * @return array{order: Order, product: Product}
     */
    protected function buildVendorOrder(Order $order, Product $product, string $status, int $rank, \DateTimeInterface $createdAt): VendorOrder
    {
        $itemTotal = round((float) $product->price, 2);
        $tax = round($itemTotal * 0.18, 2);
        $total = round($itemTotal + $tax + 50, 2);

        $vendorOrder = VendorOrder::create([
            'order_id' => $order->id,
            'vendor_id' => $product->vendor_id,
            'vendor_order_number' => 'SUB-'.strtoupper(Str::random(6)),
            'subtotal' => $itemTotal,
            'discount' => 0,
            'tax' => $tax,
            'shipping_charge' => 50,
            'cod_charge' => 0,
            'wallet_amount' => 0,
            'total' => $total,
            'status' => $status,
            'confirmed_at' => $rank >= 2 ? $createdAt->modify('+1 hour')->modify('+'.$rank.' minutes') : null,
            'packed_at' => $rank >= 4 ? $createdAt->modify('+12 hours') : null,
            'ready_to_ship_at' => $rank >= 5 ? $createdAt->modify('+1 day') : null,
            'shipped_at' => $rank >= 6 ? $createdAt->modify('+2 days') : null,
            'out_for_delivery_at' => $rank >= 7 ? $createdAt->modify('+3 days') : null,
            'delivered_at' => $rank >= 8 ? $createdAt->modify('+4 days') : null,
            'cancelled_at' => $rank === -1 ? $createdAt->modify('+1 day') : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $orderItem = $order->items()->create([
            'vendor_order_id' => $vendorOrder->id,
            'vendor_id' => $product->vendor_id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_image' => $product->images[0] ?? null,
            'unit_price' => $itemTotal,
            'quantity' => 1,
            'total' => $itemTotal,
            'commission_rate' => 10,
            'commission_amount' => round($itemTotal * 0.1, 2),
            'status' => $status,
        ]);

        VendorOrderItem::create([
            'vendor_order_id' => $vendorOrder->id,
            'order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_image' => $product->images[0] ?? null,
            'unit_price' => $itemTotal,
            'quantity' => 1,
            'total' => $itemTotal,
            'commission_rate' => 10,
            'commission_amount' => round($itemTotal * 0.1, 2),
            'status' => $status,
        ]);

        $order->update([
            'subtotal' => (float) $order->subtotal + $itemTotal,
            'tax' => (float) $order->tax + $tax,
            'shipping_charge' => (float) $order->shipping_charge + 50,
            'total' => (float) $order->total + $total,
        ]);

        return $vendorOrder;
    }

    protected function buildShipment(VendorOrder $vendorOrder, Order $order, int $rank, \DateTimeInterface $createdAt): void
    {
        $shipmentStatus = $rank >= 8 ? ShipmentStatus::Delivered : ($rank === 7 ? ShipmentStatus::OutForDelivery : ShipmentStatus::InTransit);

        $shipment = Shipment::create([
            'order_id' => $order->id,
            'vendor_order_id' => $vendorOrder->id,
            'provider' => 'shiprocket',
            'tracking_number' => 'TRK'.strtoupper(Str::random(10)),
            'courier_name' => collect(self::COURIERS)->random(),
            'status' => $shipmentStatus->value,
            'charges' => 50,
            'weight' => 0.5,
            'retry_count' => 0,
            'shipped_at' => $vendorOrder->shipped_at,
            'delivered_at' => $rank >= 8 ? $vendorOrder->delivered_at : null,
        ]);

        $timeline = match ($rank) {
            6 => [ShipmentStatus::PickupRequested, ShipmentStatus::PickedUp, ShipmentStatus::InTransit],
            7 => [ShipmentStatus::PickupRequested, ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery],
            default => [
                ShipmentStatus::PickupRequested,
                ShipmentStatus::PickedUp,
                ShipmentStatus::InTransit,
                ShipmentStatus::OutForDelivery,
                ShipmentStatus::Delivered,
            ],
        };

        foreach ($timeline as $i => $step) {
            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'status' => $step->value,
                'location' => $i % 2 === 0 ? 'Mumbai Warehouse' : 'Delhi Hub',
                'description' => $step->label().' — package scanned.',
                'is_webhook' => false,
                'occurred_at' => $shippedAt = ($vendorOrder->shipped_at ?? $createdAt)->modify('+'.$i.' hours'),
            ]);
        }
    }
}
