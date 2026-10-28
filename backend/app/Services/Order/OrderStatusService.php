<?php

namespace App\Services\Order;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Enums\WalletHoldStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Models\VendorOrder;
use App\Models\VendorOrderItem;
use App\Services\Inventory\InventoryService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Refund\RefundService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected WalletService $walletService,
        protected RefundService $refundService,
        protected NotificationDispatcher $dispatcher,
    ) {}

    /**
     * Advance a master order to the given status, rejecting invalid
     * transitions. Status history is recorded automatically by the observer.
     */
    public function changeOrderStatus(Order $order, OrderStatus|string $to, ?string $notes = null): Order
    {
        $target = $to instanceof OrderStatus ? $to : OrderStatus::tryFrom((string) $to);

        if ($target === null || $target->value === $order->order_status) {
            return $order;
        }

        if ($target === OrderStatus::Cancelled) {
            return $this->cancelOrder($order, (string) $notes);
        }

        $current = OrderStatus::tryFrom((string) $order->order_status) ?? OrderStatus::Pending;

        if (! $current->canTransitionTo($target)) {
            throw new \InvalidArgumentException("Invalid order status transition from {$current->value} to {$target->value}.");
        }

        $order->status_notes = $notes;
        $order->forceFill([
            'order_status' => $target->value,
            'cancelled_at' => $target === OrderStatus::Cancelled ? now() : $order->cancelled_at,
        ])->save();

        return $order->fresh();
    }

    /**
     * Cancel the whole master order: every pending vendor sub-order, the
     * master order and all of its items, releasing reserved stock and any
     * pending wallet hold.
     */
    public function cancelOrder(Order $order, string $reason = '', ?User $actor = null): Order
    {
        $current = OrderStatus::tryFrom((string) $order->order_status) ?? OrderStatus::Pending;

        if (! in_array($current, [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Processing], true)) {
            throw new \InvalidArgumentException('Order cannot be cancelled at this stage.');
        }

        return DB::transaction(function () use ($order, $reason, $actor): Order {
            foreach ($order->vendorOrders as $vendorOrder) {
                $this->cancelVendorOrder($vendorOrder, $reason, $actor);
            }

            OrderItem::where('order_id', $order->id)
                ->whereNot('status', OrderStatus::Cancelled->value)
                ->update(['status' => OrderStatus::Cancelled->value]);

            $order->status_notes = $reason;
            $order->forceFill([
                'order_status' => OrderStatus::Cancelled->value,
                'cancelled_at' => now(),
                'notes' => $reason,
            ])->save();

            $hold = $order->walletHolds()->where('status', WalletHoldStatus::Pending->value)->latest('id')->first();

            if ($hold) {
                $this->walletService->releaseHold($hold, $reason ?: __('Order cancelled.'));
            }

            return $order->fresh(['vendorOrders']);
        });
    }

    /**
     * Advance a vendor sub-order to the given status. Validates the
     * transition, stamps the matching fulfilment timestamp, mirrors the
     * status onto the sub-order's items, runs status-specific side effects
     * (COD collection on delivery, stock release on cancel) and finally
     * re-derives the master order status.
     */
    public function changeVendorOrderStatus(
        VendorOrder $vendorOrder,
        VendorOrderStatus|string $to,
        ?string $notes = null,
        bool $deferRecompute = false,
    ): VendorOrder {
        $target = $to instanceof VendorOrderStatus ? $to : VendorOrderStatus::tryFrom((string) $to);

        if ($target === null || $target->value === $vendorOrder->status) {
            return $vendorOrder;
        }

        $current = VendorOrderStatus::tryFrom((string) $vendorOrder->status) ?? VendorOrderStatus::Pending;

        if (! $current->canTransitionTo($target)) {
            throw new \InvalidArgumentException("Invalid order status transition from {$current->value} to {$target->value}.");
        }

        return DB::transaction(function () use ($vendorOrder, $target, $notes, $deferRecompute): VendorOrder {
            $timestampColumn = match ($target) {
                VendorOrderStatus::Confirmed => 'confirmed_at',
                VendorOrderStatus::Packed => 'packed_at',
                VendorOrderStatus::ReadyToShip => 'ready_to_ship_at',
                VendorOrderStatus::Shipped => 'shipped_at',
                VendorOrderStatus::OutForDelivery => 'out_for_delivery_at',
                VendorOrderStatus::Delivered => 'delivered_at',
                VendorOrderStatus::Cancelled => 'cancelled_at',
                default => null,
            };

            $data = ['status' => $target->value];
            if ($timestampColumn) {
                $data[$timestampColumn] = now();
            }

            $vendorOrder->status_notes = $notes;
            $vendorOrder->forceFill($data)->save();

            VendorOrderItem::where('vendor_order_id', $vendorOrder->id)->update(['status' => $target->value]);
            OrderItem::where('vendor_order_id', $vendorOrder->id)->update(['status' => $target->value]);

            $this->handleVendorOrderSideEffects($vendorOrder, $target);

            if (! $deferRecompute) {
                $this->recomputeMasterStatus($vendorOrder->order, $notes);
            }

            $this->notifyCustomerStatus($vendorOrder, $target);

            return $vendorOrder->fresh();
        });
    }

    protected function notifyCustomerStatus(VendorOrder $vendorOrder, VendorOrderStatus $target): void
    {
        $customer = $vendorOrder->order?->user;

        [$type, $title, $message] = match ($target) {
            VendorOrderStatus::Confirmed => [
                NotificationType::OrderConfirmed,
                __('Order confirmed'),
                __('Your order :order has been confirmed.', ['order' => $vendorOrder->order?->order_number]),
            ],
            VendorOrderStatus::Packed => [
                NotificationType::OrderPacked,
                __('Order packed'),
                __('Your order :order has been packed and is ready to ship.', ['order' => $vendorOrder->order?->order_number]),
            ],
            VendorOrderStatus::Shipped => [
                NotificationType::OrderShipped,
                __('Order shipped'),
                __('Your order :order has been shipped.', ['order' => $vendorOrder->order?->order_number]),
            ],
            VendorOrderStatus::OutForDelivery => [
                NotificationType::OutForDelivery,
                __('Out for delivery'),
                __('Your order :order is out for delivery.', ['order' => $vendorOrder->order?->order_number]),
            ],
            VendorOrderStatus::Delivered => [
                NotificationType::OrderDelivered,
                __('Order delivered'),
                __('Your order :order has been delivered. Thank you!', ['order' => $vendorOrder->order?->order_number]),
            ],
            VendorOrderStatus::Cancelled => [
                NotificationType::OrderCancelled,
                __('Order cancelled'),
                __('Your order :order has been cancelled.', ['order' => $vendorOrder->order?->order_number]),
            ],
            default => [null, __('Order updated'), __('The status of your order has been updated.')],
        };

        if (! $customer) {
            return;
        }

        $this->dispatcher->send(
            $customer,
            $type ?? NotificationType::OrderStatus,
            $title,
            $message,
            ['order_id' => $vendorOrder->order_id, 'vendor_order_id' => $vendorOrder->id],
        );
    }

    /**
     * Confirm every pending vendor sub-order once the master order has been
     * paid for.
     */
    public function confirmAllPendingVendorOrders(Order $order, ?string $notes = null): void
    {
        $order->loadMissing('vendorOrders');

        foreach ($order->vendorOrders as $vendorOrder) {
            if ($vendorOrder->status === VendorOrderStatus::Pending->value) {
                $this->changeVendorOrderStatus($vendorOrder, VendorOrderStatus::Confirmed, $notes ?? __('Payment received.'));
            }
        }
    }

    /**
     * Seed the initial PENDING history rows when an order is created. Later
     * transitions are captured automatically by the status observers.
     */
    public function bootstrapStatusHistory(Order $order): void
    {
        OrderStatusHistory::create([
            'historable_type' => Order::class,
            'historable_id' => $order->id,
            'to_status' => $order->order_status,
            'notes' => __('Order placed.'),
        ]);

        foreach ($order->vendorOrders as $vendorOrder) {
            OrderStatusHistory::create([
                'historable_type' => VendorOrder::class,
                'historable_id' => $vendorOrder->id,
                'to_status' => $vendorOrder->status,
                'notes' => __('Order placed.'),
            ]);
        }
    }

    /**
     * Re-derive the master order status from its vendor sub-order statuses.
     */
    public function recomputeMasterStatus(Order $order, ?string $notes = null): void
    {
        $order->loadMissing('vendorOrders');

        $statuses = $order->vendorOrders->pluck('status')->map(fn ($status): string => (string) $status)->all();

        if ($statuses === []) {
            return;
        }

        $derived = $this->deriveMasterStatus($statuses);

        if ($derived === null || $derived->value === $order->order_status) {
            return;
        }

        $current = OrderStatus::tryFrom((string) $order->order_status) ?? OrderStatus::Pending;

        if ($current->canTransitionTo($derived)) {
            $order->status_notes = $notes;
            $order->forceFill(['order_status' => $derived->value])->save();
        }
    }

    /**
     * @param  array<int, string>  $statuses
     */
    protected function deriveMasterStatus(array $statuses): ?OrderStatus
    {
        if ($statuses === []) {
            return null;
        }

        $delivered = [
            VendorOrderStatus::Delivered->value,
            VendorOrderStatus::ReturnRequested->value,
            VendorOrderStatus::Returned->value,
            VendorOrderStatus::Refunded->value,
        ];
        $shipped = [VendorOrderStatus::Shipped->value, VendorOrderStatus::OutForDelivery->value];
        $fulfilled = array_merge($shipped, $delivered);

        $all = fn (array $subset): bool => collect($statuses)->every(fn (string $status): bool => in_array($status, $subset, true));
        $any = fn (array $subset): bool => collect($statuses)->contains(fn (string $status): bool => in_array($status, $subset, true));

        if ($all([VendorOrderStatus::Cancelled->value])) {
            return OrderStatus::Cancelled;
        }

        if ($all($delivered)) {
            return OrderStatus::Delivered;
        }

        if ($any($delivered)) {
            return OrderStatus::PartiallyDelivered;
        }

        if ($all($fulfilled)) {
            return OrderStatus::Shipped;
        }

        if ($any($fulfilled)) {
            return OrderStatus::PartiallyShipped;
        }

        if ($all([VendorOrderStatus::Pending->value])) {
            return OrderStatus::Pending;
        }

        if ($all([VendorOrderStatus::Confirmed->value, VendorOrderStatus::Pending->value])) {
            return OrderStatus::Confirmed;
        }

        return OrderStatus::Processing;
    }

    protected function cancelVendorOrder(VendorOrder $vendorOrder, string $reason, ?User $actor): void
    {
        if (in_array($vendorOrder->status, [
            VendorOrderStatus::Cancelled->value,
            VendorOrderStatus::Delivered->value,
            VendorOrderStatus::Returned->value,
            VendorOrderStatus::Refunded->value,
        ], true)) {
            return;
        }

        $this->releaseVendorOrderInventory($vendorOrder);

        $this->changeVendorOrderStatus($vendorOrder, VendorOrderStatus::Cancelled, $reason ?: __('Order cancelled.'), deferRecompute: true);
    }

    protected function releaseVendorOrderInventory(VendorOrder $vendorOrder): void
    {
        foreach ($vendorOrder->items as $item) {
            if (! $item->product) {
                continue;
            }

            $variant = $item->product_variant_id ? $item->variant : null;
            $this->inventoryService->release($item->product, $variant, $item->quantity, $vendorOrder->order);
        }
    }

    protected function handleVendorOrderSideEffects(VendorOrder $vendorOrder, VendorOrderStatus $target): void
    {
        if ($target === VendorOrderStatus::Cancelled) {
            $this->releaseVendorOrderInventory($vendorOrder);

            $this->refundService->refundVendorOrderCancellation(
                $vendorOrder,
                $vendorOrder->status_notes ?: __('Order cancelled.'),
            );
        }

        if ($target === VendorOrderStatus::Delivered) {
            $this->collectCodPaymentIfDue($vendorOrder);
        }
    }

    protected function collectCodPaymentIfDue(VendorOrder $vendorOrder): void
    {
        $order = $vendorOrder->order;

        if (! $order || $order->payment_method !== PaymentMethod::Cod->value || $order->payment_status !== PaymentStatus::CodPending->value) {
            return;
        }

        $order->forceFill([
            'payment_status' => PaymentStatus::Paid->value,
            'paid_at' => now(),
        ])->save();

        $order->payments()
            ->where('gateway', 'cod')
            ->where('status', PaymentStatus::Pending->value)
            ->get()
            ->each(function ($payment): void {
                $payment->forceFill([
                    'status' => PaymentStatus::Paid->value,
                    'metadata' => ['flow' => 'cod_collected'],
                ])->save();
            });
    }
}
