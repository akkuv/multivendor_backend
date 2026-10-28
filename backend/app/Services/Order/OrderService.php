<?php

namespace App\Services\Order;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\User;
use App\Models\VendorOrder;
use App\Models\VendorOrderItem;
use App\Services\Cart\CartService;
use App\Services\Commission\CommissionService;
use App\Services\Coupon\CouponService;
use App\Services\Inventory\InventoryService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Payment\PaymentSettingsService;
use App\Services\Pricing\PricingService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        protected CartService $cartService,
        protected CouponService $couponService,
        protected CommissionService $commissionService,
        protected InventoryService $inventoryService,
        protected PricingService $pricingService,
        protected WalletService $walletService,
        protected PaymentSettingsService $paymentSettings,
        protected OrderStatusService $orderStatus,
        protected NotificationDispatcher $dispatcher,
    ) {}

    public function createFromCart(
        User $user,
        int $addressId,
        ?string $couponCode = null,
        string $paymentMethod = 'razorpay',
        ?float $walletAmount = null,
        ?string $idempotencyKey = null,
    ): Order {
        if ($idempotencyKey) {
            $existing = Order::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing->load(['items.vendor', 'shippingAddress', 'orderAddress', 'vendorOrders.vendor', 'vendorOrders.items.vendor']);
            }
        }

        $cart = $this->cartService->validateAndRefresh($user);

        if ($cart->items->isEmpty()) {
            throw new \Exception('Your cart is empty.');
        }

        $address = Address::where('user_id', $user->id)->findOrFail($addressId);

        if (! $this->paymentSettings->isMethodEnabled($paymentMethod)) {
            throw new \Exception('The selected payment method is currently unavailable.');
        }

        $breakdown = $this->pricingService->breakdown($cart, $couponCode, $paymentMethod, $walletAmount);

        if ($paymentMethod === 'wallet' && $breakdown['payable'] > 0.0001) {
            throw new \Exception('Insufficient wallet balance to complete the order.');
        }

        if ($paymentMethod === 'cod') {
            $this->assertCodEligible($breakdown['grand_total']);
        }

        if ($paymentMethod === 'cod' && ($breakdown['wallet_amount'] ?? 0) > 0) {
            throw new \Exception('Wallet balance cannot be combined with Cash on Delivery.');
        }

        $order = DB::transaction(function () use ($user, $cart, $address, $paymentMethod, $breakdown, $idempotencyKey): Order {
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-'.strtoupper(Str::random(10)),
                'idempotency_key' => $idempotencyKey,
                'subtotal' => $breakdown['subtotal'],
                'discount' => round($breakdown['discount'] + $breakdown['coupon_discount'], 2),
                'tax' => $breakdown['tax'],
                'fees_total' => $breakdown['fees_total'],
                'shipping_charge' => $breakdown['shipping'],
                'cod_charge' => $breakdown['cod_charge'],
                'wallet_amount' => $breakdown['wallet_amount'],
                'total' => $breakdown['payable'],
                'order_status' => OrderStatus::Pending->value,
                'payment_status' => PaymentStatus::Pending->value,
                'shipping_address_id' => $address->id,
                'coupon_id' => $breakdown['coupon']['id'] ?? null,
                'payment_method' => $paymentMethod,
            ]);

            OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'name' => $address->name,
                'phone' => $address->phone,
                'address_line1' => $address->address_line1,
                'address_line2' => $address->address_line2,
                'landmark' => $address->landmark,
                'city' => $address->city,
                'state' => $address->state,
                'country' => $address->country,
                'pincode' => $address->pincode,
            ]);

            $vendorGroups = collect($breakdown['vendor_groups'])->keyBy('vendor_id');

            $cart->items->groupBy(fn ($item) => $item->product?->vendor_id ?? 0)
                ->each(function ($items, $vendorId) use ($order, $vendorGroups): void {
                    $groupTotals = $vendorGroups->get((int) $vendorId);

                    $vendorOrder = $order->vendorOrders()->create([
                        'vendor_id' => (int) $vendorId,
                        'vendor_order_number' => 'SUB-'.strtoupper(Str::random(10)),
                        'subtotal' => $groupTotals['subtotal'],
                        'discount' => round($groupTotals['discount'] + $groupTotals['coupon_discount'], 2),
                        'tax' => $groupTotals['tax'],
                        'fees_total' => $groupTotals['fees_total'],
                        'shipping_charge' => $groupTotals['shipping'],
                        'cod_charge' => $groupTotals['cod_charge'],
                        'wallet_amount' => $groupTotals['wallet_amount'],
                        'total' => $groupTotals['total'],
                        'status' => OrderStatus::Pending->value,
                    ]);

                    foreach ($items as $item) {
                        $product = $item->product;
                        $pricing = $this->cartService->pricing($item);
                        $lineTotal = $pricing['subtotal'];
                        $commission = $this->commissionService->calculateFor($product->vendor, $product, $lineTotal);

                        $orderItem = $order->items()->create([
                            'vendor_order_id' => $vendorOrder->id,
                            'vendor_id' => $product->vendor_id,
                            'product_id' => $product->id,
                            'product_variant_id' => $item->product_variant_id ?? null,
                            'product_name' => $product->name,
                            'product_image' => $product->images[0] ?? null,
                            'unit_price' => $pricing['unit_price'],
                            'quantity' => $pricing['quantity'],
                            'total' => $lineTotal,
                            'fees_total' => $pricing['fees_total'],
                            'fees' => $pricing['fees'],
                            'commission_rate' => $commission['rate'],
                            'commission_amount' => $commission['commission_amount'],
                            'status' => OrderStatus::Pending->value,
                        ]);

                        VendorOrderItem::create([
                            'vendor_order_id' => $vendorOrder->id,
                            'order_item_id' => $orderItem->id,
                            'product_id' => $product->id,
                            'product_variant_id' => $item->product_variant_id ?? null,
                            'product_name' => $product->name,
                            'product_image' => $product->images[0] ?? null,
                            'unit_price' => $pricing['unit_price'],
                            'quantity' => $pricing['quantity'],
                            'total' => $lineTotal,
                            'fees_total' => $pricing['fees_total'],
                            'fees' => $pricing['fees'],
                            'commission_rate' => $commission['rate'],
                            'commission_amount' => $commission['commission_amount'],
                            'status' => OrderStatus::Pending->value,
                        ]);

                        $variant = $item->product_variant_id ? $item->variant : null;
                        $this->inventoryService->reserve($product, $variant, $item->quantity, $orderItem, $order->user_id);
                    }
                });

            $this->orderStatus->bootstrapStatusHistory($order);

            if ($breakdown['coupon']) {
                $this->couponService->markUsed($breakdown['coupon'], $user, $order->id, $breakdown['coupon_discount']);
            }

            if ($breakdown['wallet_amount'] > 0) {
                $this->walletService->hold(
                    $user,
                    $breakdown['wallet_amount'],
                    $order,
                    "Payment for order {$order->order_number}.",
                    now()->addHours(24),
                );
            }

            $this->cartService->clear($user);

            $order->load(['items.vendor', 'shippingAddress', 'orderAddress', 'vendorOrders.vendor', 'vendorOrders.items.vendor']);

            return $order;
        });

        OrderPlaced::dispatch($order);

        $this->notifyPartiesOfPlacement($order, $user);

        return $order;
    }

    /**
     * Tell the customer the order went through and every vendor whose stock is
     * in it that there is a new order to fulfil.
     */
    private function notifyPartiesOfPlacement(Order $order, User $customer): void
    {
        $this->dispatcher->send(
            $customer,
            NotificationType::OrderCreated,
            __('Order placed'),
            __('Your order :order has been placed.', ['order' => $order->order_number]),
            ['order_id' => $order->id],
        );

        $order->vendorOrders->each(function (VendorOrder $vendorOrder) use ($order): void {
            $vendorUser = $vendorOrder->vendor?->user;

            if ($vendorUser === null) {
                return;
            }

            $this->dispatcher->send(
                $vendorUser,
                NotificationType::NewOrderForVendor,
                __('New order received'),
                __('You have a new order :order to fulfil.', ['order' => $order->order_number]),
                [
                    'order_id' => $order->id,
                    'vendor_order_id' => $vendorOrder->id,
                    'vendor_order_number' => $vendorOrder->vendor_order_number,
                ],
            );
        });
    }

    public function cancel(User $user, Order $order, string $reason = ''): Order
    {
        if ($order->user_id !== $user->id) {
            throw new \Exception('You do not have access to this order.');
        }

        $order = $this->orderStatus->cancelOrder($order, $reason);

        return $order->load('items', 'vendorOrders');
    }

    protected function assertCodEligible(float $grandTotal): void
    {
        $bounds = $this->paymentSettings->codBounds();

        if ($bounds['min'] > 0 && $grandTotal < $bounds['min']) {
            throw new \Exception('Cash on Delivery is not available for orders below '.number_format($bounds['min'], 2).'.');
        }

        if ($bounds['max'] !== null && $grandTotal > $bounds['max']) {
            throw new \Exception('Cash on Delivery is not available for orders above '.number_format($bounds['max'], 2).'.');
        }
    }
}
