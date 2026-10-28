<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\Refund;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\Return\ReturnService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ReturnFlowSeeder extends Seeder
{
    public function run(ReturnService $returnService): void
    {
        if (ProductReturn::query()->exists()) {
            $this->command?->info('Returns already seeded. Skipping.');

            return;
        }

        $customers = User::query()->where('role', 'CUSTOMER')->get();
        $vendors = VendorProfile::query()->where('is_approved', true)->get();

        if ($customers->isEmpty() || $vendors->isEmpty()) {
            $this->command?->warn('Need at least one customer and one approved vendor to seed returns.');

            return;
        }

        $products = Product::query()->take(12)->get();
        $created = 0;

        // Statuses that exercise the full lifecycle.
        $targets = [
            ReturnStatus::Requested,
            ReturnStatus::Approved,
            ReturnStatus::PickupScheduled,
            ReturnStatus::PickedUp,
            ReturnStatus::Received,
            ReturnStatus::Inspecting,
            ReturnStatus::ApprovedForRefund,
            ReturnStatus::Refunded,
            ReturnStatus::Closed,
        ];

        foreach ($targets as $index => $target) {
            $customer = $customers->get($index % $customers->count());
            $vendor = $vendors->get($index % $vendors->count());
            $product = $products->get($index % $products->count());

            $order = $this->deliveredOrder($customer, $vendor, $product);
            $orderItem = $order->items()->first();

            $pickup = Address::create([
                'user_id' => $customer->id,
                'type' => 'home',
                'name' => $customer->name,
                'phone' => $customer->phone ?? '9876543210',
                'address_line1' => 'Shop '.($index + 1).', MG Road',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
                'pincode' => '400001',
                'is_default' => false,
            ]);

            $ret = ProductReturn::create([
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'user_id' => $customer->id,
                'quantity' => $orderItem->quantity,
                'reason' => $this->reason($index),
                'description' => 'Reached us '.$index.' days after delivery. Please arrange pickup.',
                'images' => [],
                'status' => ReturnStatus::Requested->value,
                'status_notes' => null,
                'pickup_address_id' => $pickup->id,
            ]);

            // Walk the flow to the desired status so timestamps/refunds are correct.
            $current = ReturnStatus::Requested;
            $indexForTarget = array_search($target->value, array_column(ReturnStatus::cases(), 'value'), true);
            $path = $this->pathTo($target);

            foreach ($path as $step) {
                if ($step === $current->value || ! $current->canTransitionTo(ReturnStatus::tryFrom($step))) {
                    continue;
                }
                $returnService->transitionTo(
                    $ret,
                    ReturnStatus::tryFrom($step),
                    'Seeded flow: '.Str::lower(ReturnStatus::tryFrom($step)->label()),
                );
                $current = ReturnStatus::tryFrom($step);
            }

            $created++;
        }

        // Standalone refunds at varying states for the Refunds tab.
        for ($i = 0; $i < 4; $i++) {
            $customer = $customers->get($i % $customers->count());
            $order = $this->paidOrder($customer);
            $status = match ($i % 4) {
                0 => RefundStatus::Pending,
                1 => RefundStatus::Approved,
                2 => RefundStatus::Initiated,
                default => RefundStatus::Completed,
            };
            Refund::create([
                'order_id' => $order->id,
                'user_id' => $customer->id,
                'amount' => round(300 + $i * 150, 2),
                'quantity' => 1,
                'status' => $status->value,
                'method' => $status === RefundStatus::Completed ? 'Razorpay' : null,
                'wallet_amount' => 0,
                'gateway_amount' => $status === RefundStatus::Completed ? round(300 + $i * 150, 2) : 0,
                'reason' => 'Seed refund for order '.$order->order_number,
                'razorpay_refund_id' => $status === RefundStatus::Completed ? 'rfnd_seed_'.$i : null,
                'processed_at' => $status === RefundStatus::Completed ? now()->subHours($i) : null,
            ]);
        }

        $this->command?->info("Seeded {$created} returns across all lifecycle statuses.");
    }

    protected function pathTo(ReturnStatus $target): array
    {
        return match ($target) {
            ReturnStatus::Requested => ['REQUESTED'],
            ReturnStatus::Approved => ['REQUESTED', 'APPROVED'],
            ReturnStatus::PickupScheduled => ['REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED'],
            ReturnStatus::PickedUp => ['REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED', 'PICKED_UP'],
            ReturnStatus::Received => ['REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'RECEIVED'],
            ReturnStatus::Inspecting => ['REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'RECEIVED', 'INSPECTING'],
            ReturnStatus::ApprovedForRefund => ['REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'RECEIVED', 'INSPECTING', 'APPROVED_FOR_REFUND'],
            ReturnStatus::Refunded => ['REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'RECEIVED', 'INSPECTING', 'APPROVED_FOR_REFUND', 'REFUNDED'],
            ReturnStatus::Closed => ['REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'RECEIVED', 'INSPECTING', 'APPROVED_FOR_REFUND', 'REFUNDED', 'CLOSED'],
            default => ['REQUESTED'],
        };
    }

    protected function reason(int $index): string
    {
        $reasons = ['Wrong item delivered', 'Damaged on arrival', 'Not as described', 'Sizing issue', 'Defective product'];

        return $reasons[$index % count($reasons)];
    }

    protected function deliveredOrder(User $customer, VendorProfile $vendor, Product $product): Order
    {
        $order = $this->paidOrder($customer);
        $order->update([
            'order_status' => OrderStatus::Delivered->value,
            'order_number' => 'ORD-RT-'.strtoupper(Str::random(6)),
        ]);
        $order->items()->create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_image' => $product->images[0] ?? null,
            'unit_price' => $product->price,
            'quantity' => 1,
            'total' => $product->price,
            'commission_rate' => 10,
            'commission_amount' => round($product->price * 0.1, 2),
            'status' => OrderStatus::Delivered->value,
        ]);

        $itemTotal = (float) $product->price;
        $order->update([
            'subtotal' => $itemTotal,
            'discount' => 0,
            'tax' => round($itemTotal * 0.18, 2),
            'shipping_charge' => 50,
            'total' => round($itemTotal + 50 + $itemTotal * 0.18, 2),
        ]);

        return $order->refresh();
    }

    protected function paidOrder(User $customer): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-'.strtoupper(Str::random(10)),
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
            'razorpay_payment_id' => 'pay_seed_'.Str::random(8),
            'paid_at' => now()->subDays(7),
        ]);
    }
}
