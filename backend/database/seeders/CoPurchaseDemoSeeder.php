<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorOrder;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Seeds delivered, multi-item orders so the "Complete Your Look"
 * (frequently-bought-together) rail has real co-purchase signal on the PDP.
 *
 * Companion pieces are drawn from the same department, so the pairings read
 * naturally (a lehenga with its dupatta and jewellery, a sherwani with its
 * accessories) and the rail returns sensible recommendations.
 */
class CoPurchaseDemoSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('role', Role::Customer)->get();

        $products = Product::query()
            ->whereNotNull('vendor_id')
            ->get(['id', 'vendor_id', 'category_id', 'name', 'price', 'images']);

        if ($customers->isEmpty() || $products->count() < 4) {
            return;
        }

        $vendors = VendorProfile::query()->get(['id', 'commission_rate'])->keyBy('id');

        $index = 0;

        foreach ($products->groupBy('category_id') as $department) {
            if ($department->count() < 3) {
                continue;
            }

            foreach ($department as $product) {
                $take = min(2, $department->count() - 1);
                $companions = $department
                    ->where('id', '!=', $product->id)
                    ->random($take);

                /** @var Collection<int, Product> $items */
                $items = collect([$product])->concat($companions)->unique('id')->values();

                $this->createDeliveredOrder($customers->random(), $items, $vendors, $index);
                $index++;
            }
        }

        $this->command?->info('Seeded '.$index.' co-purchase orders across '.$products->count().' products.');
    }

    /**
     * @param  Collection<int, Product>  $items
     * @param  Collection<int, VendorProfile>  $vendors
     */
    private function createDeliveredOrder(User $customer, Collection $items, Collection $vendors, int $index): void
    {
        $createdAt = now()->subDays(3 + ($index % 25))->setTime(9 + ($index % 8), 15);
        $shipping = 50.0;

        $subtotal = round((float) $items->sum(fn (Product $item): float => (float) $item->price), 2);
        $tax = round($subtotal * 0.18, 2);
        $total = round($subtotal + $tax + $shipping, 2);

        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-CP-'.strtoupper(Str::random(6)),
            'idempotency_key' => (string) Str::uuid(),
            'subtotal' => $subtotal,
            'discount' => 0,
            'tax' => $tax,
            'shipping_charge' => $shipping,
            'cod_charge' => 0,
            'wallet_amount' => 0,
            'total' => $total,
            'order_status' => OrderStatus::Delivered->value,
            'payment_status' => PaymentStatus::Paid->value,
            'payment_method' => 'razorpay',
            'razorpay_payment_id' => 'pay_cp_'.Str::random(8),
            'paid_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        foreach ($items->groupBy('vendor_id') as $vendorId => $vendorItems) {
            $vendor = $vendors->get((int) $vendorId);

            if ($vendor === null) {
                continue;
            }

            $lineSubtotal = round((float) $vendorItems->sum(fn (Product $item): float => (float) $item->price), 2);
            $lineTax = round($lineSubtotal * 0.18, 2);
            $lineTotal = round($lineSubtotal + $lineTax, 2);

            $vendorOrder = VendorOrder::create([
                'order_id' => $order->id,
                'vendor_id' => $vendor->id,
                'vendor_order_number' => 'SUB-'.strtoupper(Str::random(6)),
                'subtotal' => $lineSubtotal,
                'discount' => 0,
                'tax' => $lineTax,
                'shipping_charge' => 0,
                'cod_charge' => 0,
                'wallet_amount' => 0,
                'total' => $lineTotal,
                'status' => OrderStatus::Delivered->value,
                'confirmed_at' => $createdAt->copy()->addHours(1),
                'packed_at' => $createdAt->copy()->addHours(6),
                'ready_to_ship_at' => $createdAt->copy()->addDay(),
                'shipped_at' => $createdAt->copy()->addDays(2),
                'delivered_at' => $createdAt->copy()->addDays(3),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            foreach ($vendorItems as $product) {
                $unitPrice = round((float) $product->price, 2);

                $vendorOrder->items()->create([
                    'order_id' => $order->id,
                    'vendor_id' => $vendor->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_image' => $product->images[0] ?? null,
                    'unit_price' => $unitPrice,
                    'quantity' => 1,
                    'total' => $unitPrice,
                    'commission_rate' => $vendor->commission_rate,
                    'commission_amount' => round($unitPrice * (float) $vendor->commission_rate / 100, 2),
                    'status' => OrderStatus::Delivered->value,
                ]);
            }
        }
    }
}
