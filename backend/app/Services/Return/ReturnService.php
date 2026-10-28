<?php

namespace App\Services\Return;

use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Events\ReturnRequested;
use App\Models\Order;
use App\Models\ProductReturn;
use App\Models\User;
use App\Services\Refund\RefundService;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    public function __construct(protected RefundService $refundService) {}

    public function request(User $user, Order $order, int $orderItemId, array $data): ProductReturn
    {
        if ($order->user_id !== $user->id) {
            throw new \Exception('You do not have access to this order.');
        }

        $item = $order->items()->findOrFail($orderItemId);

        if (! in_array($order->order_status, [OrderStatus::Delivered->value], true)) {
            throw new \Exception('Return is only allowed for delivered orders.');
        }

        if ($item->product && ! (bool) $item->product->is_returnable) {
            throw new \Exception('This product is not returnable.');
        }

        if ($item->productReturns()->whereNotIn('status', [ReturnStatus::Closed->value])->exists()) {
            throw new \Exception('A return request already exists for this item.');
        }

        $quantity = (int) (data_get($data, 'quantity', 0)) ?: 1;

        if ($quantity < 1 || $quantity > (int) $item->quantity) {
            throw new \Exception('Return quantity cannot exceed the purchased quantity.');
        }

        return DB::transaction(function () use ($order, $item, $user, $data, $quantity): ProductReturn {
            $productReturn = $item->productReturns()->create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'quantity' => $quantity,
                'reason' => $data['reason'],
                'description' => $data['description'] ?? null,
                'images' => data_get($data, 'images', []),
                'status' => ReturnStatus::Requested->value,
                'pickup_address_id' => $data['pickup_address_id'] ?? null,
            ]);

            ReturnRequested::dispatch($productReturn);

            $this->recomputeOrderStatus($order);

            return $productReturn;
        });
    }

    /**
     * Move a return along its lifecycle. Validates the transition against the
     * state machine, stamps the matching timestamps and triggers side effects
     * (e.g. creating the PENDING refund once approved for refund).
     */
    public function transitionTo(
        ProductReturn $productReturn,
        ReturnStatus $target,
        ?string $notes = null,
        bool $autoRefund = true,
    ): ProductReturn {
        $current = ReturnStatus::tryFrom((string) $productReturn->status) ?? ReturnStatus::Requested;

        if (! $current->canTransitionTo($target)) {
            throw new \InvalidArgumentException(
                "Invalid return status transition from {$current->value} to {$target->value}.",
            );
        }

        return DB::transaction(function () use ($productReturn, $target, $notes, $autoRefund): ProductReturn {
            $productReturn->forceFill([
                'status' => $target->value,
                'status_notes' => $notes ?? $productReturn->status_notes,
            ])->save();

            match ($target) {
                ReturnStatus::PickupScheduled => $productReturn->forceFill(['pickup_scheduled_at' => now()])->save(),
                ReturnStatus::PickedUp => $productReturn->forceFill(['picked_up_at' => now()])->save(),
                ReturnStatus::Received => $productReturn->forceFill(['received_at' => now()])->save(),
                ReturnStatus::Inspecting => $productReturn->forceFill(['inspecting_at' => now()])->save(),
                ReturnStatus::ApprovedForRefund => $this->approveForRefund($productReturn, $autoRefund),
                ReturnStatus::Refunded => $productReturn->forceFill(['refunded_at' => now()])->save(),
                ReturnStatus::Closed => $productReturn->forceFill(['closed_at' => now()])->save(),
                default => null,
            };

            $this->recomputeOrderStatus($productReturn->order);

            return $productReturn->refresh();
        });
    }

    /**
     * Customer withdrawal of a request that has not yet been acted upon.
     */
    public function withdraw(ProductReturn $productReturn): ProductReturn
    {
        return $this->transitionTo($productReturn, ReturnStatus::Closed, 'Return request withdrawn by customer.');
    }

    protected function approveForRefund(ProductReturn $productReturn, bool $autoRefund): void
    {
        $productReturn->forceFill(['approved_for_refund_at' => now()])->save();

        if ($autoRefund) {
            $this->refundService->initiateForReturn($productReturn);
        }
    }

    /**
     * Mirror the aggregate return state back onto the master order. While any
     * item has an active return the order is marked RETURN_REQUESTED; once
     * every return is terminal the order returns to DELIVERED (a later refund
     * completion flips it to RETURNED/REFUNDED as appropriate).
     */
    protected function recomputeOrderStatus(Order $order): void
    {
        if (in_array($order->order_status, [OrderStatus::Returned->value, OrderStatus::Refunded->value], true)) {
            return;
        }

        $active = $order->returns()
            ->whereNotIn('status', [ReturnStatus::Refunded->value, ReturnStatus::Closed->value])
            ->exists();

        $target = $active
            ? OrderStatus::ReturnRequested
            : (in_array($order->order_status, [OrderStatus::ReturnRequested->value], true)
                ? OrderStatus::Delivered
                : null);

        if ($target) {
            $order->forceFill(['order_status' => $target->value])->save();
        }
    }
}
