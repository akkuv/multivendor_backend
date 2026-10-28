<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentPickupStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Models\VendorOrder;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds a shipment for each fulfilment status so the vendor Shipments page
 * demonstrates the full tracking lifecycle with working detail pages. Skips a
 * store once it already has shipments, so the seeder is safe to re-run.
 */
class VendorShipmentDemoSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const COURIERS = ['Delhivery', 'Blue Dart', 'Ecom Express', 'DTDC', 'Shiprocket'];

    /**
     * @var array<int, ShipmentStatus>
     */
    private const STATUSES = [
        ShipmentStatus::AwbGenerated,
        ShipmentStatus::PickupRequested,
        ShipmentStatus::PickupScheduled,
        ShipmentStatus::PickedUp,
        ShipmentStatus::InTransit,
        ShipmentStatus::OutForDelivery,
        ShipmentStatus::Reattempting,
        ShipmentStatus::Delivered,
        ShipmentStatus::Cancelled,
        ShipmentStatus::Failed,
    ];

    public function run(): void
    {
        $vendors = VendorProfile::query()->where('is_approved', true)->get();
        $customers = User::query()->where('role', 'CUSTOMER')->get();

        if ($vendors->isEmpty() || $customers->isEmpty()) {
            $this->command?->warn('Need at least one approved vendor and one customer to seed shipments.');

            return;
        }

        $created = 0;

        foreach ($vendors as $vendor) {
            if ($this->vendorHasShipments($vendor->id)) {
                continue;
            }

            $products = Product::query()
                ->where('vendor_id', $vendor->id)
                ->where('is_returnable', true)
                ->latest('id')
                ->get();

            if ($products->isEmpty()) {
                $this->command?->warn("Store {$vendor->store_name} has no products. Skipping.");

                continue;
            }

            $index = 0;

            foreach (self::STATUSES as $status) {
                $product = $products->get($index % $products->count());
                $customer = $customers->get($index % $customers->count());

                [$order, $vendorOrder] = $this->fulfilledOrder($customer, $vendor, $product, $index);
                $shipment = $this->createShipment($order, $vendorOrder, $product, $status, $index);
                $this->createEvents($shipment, $status);

                $created++;
                $index++;
            }
        }

        $this->command?->info("Seeded {$created} shipments across vendor stores.");
    }

    protected function vendorHasShipments(int $vendorId): bool
    {
        return Shipment::query()
            ->whereIn('vendor_order_id', VendorOrder::query()->where('vendor_id', $vendorId)->select('id'))
            ->exists();
    }

    protected function createShipment(
        Order $order,
        VendorOrder $vendorOrder,
        Product $product,
        ShipmentStatus $status,
        int $index,
    ): Shipment {
        $isPickupStage = in_array($status, [ShipmentStatus::PickupRequested, ShipmentStatus::PickupScheduled], true);

        return Shipment::create([
            'order_id' => $order->id,
            'vendor_order_id' => $vendorOrder->id,
            'provider' => 'shiprocket',
            'shiprocket_order_id' => (string) mt_rand(100000, 999999),
            'shipment_id' => (string) mt_rand(100000, 999999),
            'tracking_number' => 'SH'.strtoupper(Str::random(2)).str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT).Str::random(3).'IN',
            'courier_name' => collect(self::COURIERS)->random(),
            'tracking_url' => null,
            'pickup_status' => $isPickupStage ? ShipmentPickupStatus::Scheduled->value : ShipmentPickupStatus::None->value,
            'status' => $status->value,
            'charges' => round(50 + $index * 7, 2),
            'weight' => round(0.5 + $index * 0.4, 2),
            'retry_count' => $status === ShipmentStatus::Failed ? 2 : 0,
            'error_message' => $status === ShipmentStatus::Failed ? 'Courier pickup declined. Please retry the shipment.' : null,
            'shipped_at' => $this->statusToTimestamp($order, $status, 'shipped_at'),
            'pickup_requested_at' => in_array($status, [ShipmentStatus::PickupRequested], true) ? $order->created_at->copy()->addHours(4) : null,
            'delivered_at' => $status === ShipmentStatus::Delivered ? $order->created_at->copy()->addDays(4) : null,
            'created_at' => $order->created_at,
            'updated_at' => $order->created_at,
        ]);
    }

    /**
     * @return array{Order, VendorOrder}
     */
    protected function fulfilledOrder(User $customer, VendorProfile $vendor, Product $product, int $index): array
    {
        $createdAt = now()->subDays(6 - ($index % 6))->setTime(9 + ($index % 8), 30);
        $itemTotal = round((float) $product->price, 2);
        $tax = round($itemTotal * 0.18, 2);
        $total = round($itemTotal + $tax + 50, 2);

        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-SH-'.strtoupper(Str::random(6)),
            'idempotency_key' => (string) Str::uuid(),
            'subtotal' => $itemTotal,
            'discount' => 0,
            'tax' => $tax,
            'shipping_charge' => 50,
            'cod_charge' => 0,
            'wallet_amount' => 0,
            'total' => $total,
            'order_status' => OrderStatus::Delivered->value,
            'payment_status' => PaymentStatus::Paid->value,
            'payment_method' => 'razorpay',
            'razorpay_payment_id' => 'pay_sh_'.Str::random(8),
            'paid_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $vendorOrder = VendorOrder::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'vendor_order_number' => 'SUB-'.strtoupper(Str::random(6)),
            'subtotal' => $itemTotal,
            'discount' => 0,
            'tax' => $tax,
            'shipping_charge' => 50,
            'cod_charge' => 0,
            'wallet_amount' => 0,
            'total' => $total,
            'status' => OrderStatus::Delivered->value,
            'confirmed_at' => $createdAt->copy()->addHours(1),
            'packed_at' => $createdAt->copy()->addHours(6),
            'ready_to_ship_at' => $createdAt->copy()->addDay(),
            'shipped_at' => $createdAt->copy()->addDays(2),
            'out_for_delivery_at' => $createdAt->copy()->addDays(3),
            'delivered_at' => $createdAt->copy()->addDays(4),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $order->items()->create([
            'vendor_order_id' => $vendorOrder->id,
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_image' => $product->images[0] ?? null,
            'unit_price' => $itemTotal,
            'quantity' => 1,
            'total' => $itemTotal,
            'commission_rate' => $vendor->commission_rate,
            'commission_amount' => round($itemTotal * $vendor->commission_rate / 100, 2),
            'status' => OrderStatus::Delivered->value,
        ]);

        return [$order, $vendorOrder];
    }

    /**
     * @return array<int, ShipmentStatus>
     */
    protected function pathTo(ShipmentStatus $target): array
    {
        $steps = [
            ShipmentStatus::PickupRequested,
            ShipmentStatus::PickupScheduled,
            ShipmentStatus::PickedUp,
            ShipmentStatus::InTransit,
            ShipmentStatus::OutForDelivery,
            ShipmentStatus::Delivered,
        ];

        return match ($target) {
            ShipmentStatus::AwbGenerated => [ShipmentStatus::AwbGenerated],
            ShipmentStatus::Cancelled => [ShipmentStatus::PickupRequested, ShipmentStatus::Cancelled],
            ShipmentStatus::Failed => [ShipmentStatus::PickupRequested, ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::Failed],
            default => array_slice($steps, 0, array_search($target, $steps, true) + 1),
        };
    }

    protected function createEvents(Shipment $shipment, ShipmentStatus $status): void
    {
        $occurredAt = ($shipment->created_at ?? now())->copy();
        $hasLocation = ! in_array($status, [ShipmentStatus::AwbGenerated, ShipmentStatus::Cancelled], true);

        foreach ($this->pathTo($status) as $i => $step) {
            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'status' => $step->value,
                'location' => $hasLocation ? ($i % 2 === 0 ? 'Mumbai Warehouse' : 'Delhi Hub') : null,
                'description' => $step === ShipmentStatus::Failed
                    ? 'Pickup failed — courier reported an issue.'
                    : $step->label().' — package scanned.',
                'is_webhook' => false,
                'occurred_at' => $occurredAt->copy()->addHours($i * 8),
            ]);
        }
    }

    protected function statusToTimestamp(Order $order, ShipmentStatus $status, string $field): ?\DateTimeInterface
    {
        if ($field === 'shipped_at') {
            return in_array($status, [ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery, ShipmentStatus::Reattempting, ShipmentStatus::Delivered, ShipmentStatus::Failed], true)
                ? $order->created_at->copy()->addDays(2)
                : null;
        }

        return null;
    }
}
