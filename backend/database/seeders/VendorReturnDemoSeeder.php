<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\Return\ReturnService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds return requests so the vendor Returns page demonstrates the full
 * lifecycle. Each approved store receives one return request per status
 * (REQUESTED through CLOSED); existing returns are preserved and only the
 * missing statuses are filled in, so the seeder is safe to re-run.
 */
class VendorReturnDemoSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const REASONS = [
        'Wrong item delivered',
        'Damaged on arrival',
        'Not as described',
        'Sizing issue',
        'Defective product',
        'Changed my mind',
        'Item stopped working',
        'Received a used item',
        'Colour mismatch',
    ];

    /**
     * @var array<int, string>
     */
    private const NOTES = [
        ReturnStatus::Approved->value => 'Return approved. Pickup will be arranged within 48 hours.',
        ReturnStatus::Rejected->value => 'Return rejected — item does not qualify. Contact support for details.',
        ReturnStatus::PickupScheduled->value => 'Pickup scheduled — courier arrives within 2 business days.',
        ReturnStatus::PickedUp->value => 'Item picked up by the courier.',
        ReturnStatus::Received->value => 'Item received at the returns centre.',
        ReturnStatus::Inspecting->value => 'Quality check in progress.',
        ReturnStatus::ApprovedForRefund->value => 'Refund approved — amount will be credited shortly.',
        ReturnStatus::Refunded->value => 'Refund credited to the customer.',
        ReturnStatus::Closed->value => 'Return request closed.',
    ];

    public function run(ReturnService $returnService): void
    {
        $vendors = VendorProfile::query()->where('is_approved', true)->get();
        $customers = User::query()->where('role', 'CUSTOMER')->get();

        if ($vendors->isEmpty() || $customers->isEmpty()) {
            $this->command?->warn('Need at least one approved vendor and one customer to seed returns.');

            return;
        }

        $created = 0;

        foreach ($vendors as $vendor) {
            $existing = $this->existingStatuses($vendor->id);

            $products = Product::query()
                ->where('vendor_id', $vendor->id)
                ->where('is_returnable', true)
                ->latest('id')
                ->get();

            if ($products->isEmpty()) {
                $this->command?->warn("Store {$vendor->store_name} has no returnable products. Skipping.");

                continue;
            }

            $index = 0;

            foreach (ReturnStatus::cases() as $status) {
                if (in_array($status->value, $existing, true)) {
                    continue;
                }

                $product = $products->get($index % $products->count());
                $customer = $customers->get($index % $customers->count());
                $order = $this->deliveredOrder($customer, $vendor, $product);
                $item = $order->items()->first();
                $pickup = $this->pickupAddress($customer, $vendor, $index);

                $productReturn = ProductReturn::create([
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'user_id' => $customer->id,
                    'quantity' => $order->items()->first()->quantity, // qty purchased
                    'reason' => self::REASONS[$index % count(self::REASONS)],
                    'description' => 'Ordered '.$item->product_name.'. '.$this->reason($index).'. Please arrange pickup.',
                    'images' => array_slice((array) $product->images, 0, 2),
                    'status' => ReturnStatus::Requested->value,
                    'status_notes' => null,
                    'pickup_address_id' => $pickup->id,
                ]);

                $current = ReturnStatus::Requested;

                foreach ($this->pathTo($status) as $step) {
                    $step = ReturnStatus::tryFrom($step);

                    if (! $step || $step === $current || ! $current->canTransitionTo($step)) {
                        continue;
                    }

                    $notes = self::NOTES[$step->value] ?? null;

                    if ($step === ReturnStatus::Refunded) {
                        $notes = 'Refund of ₹'.number_format((float) $item->total, 2).' credited to the customer wallet.';
                    }

                    $returnService->transitionTo($productReturn, $step, $notes);
                    $current = $step;
                }

                $created++;
                $index++;
            }
        }

        $this->command?->info("Seeded {$created} return requests across vendor stores.");
    }

    /**
     * @return array<int, string>
     */
    protected function existingStatuses(int $vendorId): array
    {
        return ProductReturn::query()
            ->join('order_items', 'order_items.id', '=', 'product_returns.order_item_id')
            ->where('order_items.vendor_id', $vendorId)
            ->pluck('product_returns.status')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function pathTo(ReturnStatus $target): array
    {
        return match ($target) {
            ReturnStatus::Rejected => ['REQUESTED', 'REJECTED'],
            ReturnStatus::Approved => ['REQUESTED', 'APPROVED'],
            ReturnStatus::PickupScheduled => ['REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED'],
            ReturnStatus::PickedUp => ['REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED', 'PICKED_UP'],
            ReturnStatus::Received => ['REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'RECEIVED'],
            ReturnStatus::Inspecting => ['REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'RECEIVED', 'INSPECTING'],
            ReturnStatus::ApprovedForRefund => ['REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'RECEIVED', 'INSPECTING', 'APPROVED_FOR_REFUND'],
            ReturnStatus::Refunded => ['REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'RECEIVED', 'INSPECTING', 'APPROVED_FOR_REFUND', 'REFUNDED'],
            ReturnStatus::Closed => ['REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'RECEIVED', 'INSPECTING', 'APPROVED_FOR_REFUND', 'REFUNDED', 'CLOSED'],
            default => [],
        };
    }

    protected function reason(int $index): string
    {
        return self::REASONS[$index % count(self::REASONS)];
    }

    protected function pickupAddress(User $customer, VendorProfile $vendor, int $index): Address
    {
        return Address::create([
            'user_id' => $customer->id,
            'type' => 'home',
            'name' => $customer->name,
            'phone' => $customer->phone ?? '9876543210',
            'address_line1' => 'Flat '.($index + 1).', Andheri West',
            'city' => $vendor->city ?? 'Mumbai',
            'state' => $vendor->state ?? 'Maharashtra',
            'country' => $vendor->country ?? 'India',
            'pincode' => $vendor->pincode ?? '400001',
            'is_default' => false,
        ]);
    }

    protected function deliveredOrder(User $customer, VendorProfile $vendor, Product $product): Order
    {
        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-RT-'.strtoupper(Str::random(6)),
            'idempotency_key' => (string) Str::uuid(),
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'shipping_charge' => 0,
            'cod_charge' => 0,
            'wallet_amount' => 0,
            'total' => 0,
            'order_status' => OrderStatus::Delivered->value,
            'payment_status' => PaymentStatus::Paid->value,
            'payment_method' => 'razorpay',
            'razorpay_payment_id' => 'pay_rt_'.Str::random(8),
            'paid_at' => now()->subDays(9),
            'created_at' => now()->subDays(12),
            'updated_at' => now()->subDays(12),
        ]);

        $itemTotal = round((float) $product->price, 2);
        $quantity = ($order->id % 2) + 1;
        $tax = round($itemTotal * $quantity * 0.18, 2);
        $total = round($itemTotal * $quantity + $tax + 50, 2);

        $order->items()->create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_image' => $product->images[0] ?? null,
            'unit_price' => $itemTotal,
            'quantity' => $quantity,
            'total' => $itemTotal * $quantity,
            'commission_rate' => $vendor->commission_rate,
            'commission_amount' => round($itemTotal * $quantity * $vendor->commission_rate / 100, 2),
            'status' => OrderStatus::Delivered->value,
        ]);

        $order->update([
            'subtotal' => $itemTotal * $quantity,
            'discount' => 0,
            'tax' => $tax,
            'shipping_charge' => 50,
            'total' => $total,
        ]);

        return $order->refresh();
    }
}
