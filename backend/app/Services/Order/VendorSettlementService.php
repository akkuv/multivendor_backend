<?php

namespace App\Services\Order;

use App\Enums\CommissionTransactionType;
use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnStatus;
use App\Enums\VendorCommissionStatus;
use App\Enums\VendorOrderStatus;
use App\Enums\VendorWalletTransactionType;
use App\Models\CommissionTransaction;
use App\Models\OrderItem;
use App\Models\ProductReturn;
use App\Models\Refund;
use App\Models\VendorCommission;
use App\Models\VendorOrder;
use App\Models\VendorProfile;
use App\Services\Wallet\VendorWalletService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VendorSettlementService
{
    public function __construct(protected VendorWalletService $walletService) {}

    /**
     * Finalize settlement for every delivered vendor order whose return
     * window has elapsed. Returns the number of orders settled.
     */
    public function settleEligible(?Carbon $asOf = null): int
    {
        $asOf = $asOf ?? now();

        $eligible = $this->eligibleVendorOrders($asOf);

        $settled = 0;

        foreach ($eligible as $vendorOrder) {
            $this->settle($vendorOrder, $asOf);
            $settled++;
        }

        return $settled;
    }

    /**
     * Finalize one delivered order: freeze the commission, create the vendor
     * payable ledger entry and credit the vendor wallet.
     */
    public function settle(VendorOrder $vendorOrder, ?Carbon $asOf = null, bool $requireDelivered = true): VendorCommission
    {
        if ($requireDelivered && ($vendorOrder->status !== VendorOrderStatus::Delivered->value || ! $vendorOrder->delivered_at)) {
            throw new RuntimeException(__('Only delivered orders can be settled.'));
        }

        if ($vendorOrder->vendorCommission()->exists()) {
            throw new RuntimeException(__('Vendor order is already settled.'));
        }

        if ($this->hasOpenReturn($vendorOrder)) {
            throw new RuntimeException(__('Vendor order has an in-progress return.'));
        }

        $asOf = $asOf ?? now();
        $totals = $this->settlementTotals($vendorOrder);

        return DB::transaction(function () use ($vendorOrder, $asOf, $totals): VendorCommission {
            $commission = VendorCommission::create([
                'vendor_id' => $vendorOrder->vendor_id,
                'order_id' => $vendorOrder->order_id,
                'vendor_order_id' => $vendorOrder->id,
                'eligible_amount' => $totals['eligible_amount'],
                'discount_amount' => $totals['discount_amount'],
                'commission_rate' => $totals['commission_rate'],
                'commission_amount' => $totals['commission_amount'],
                'net_payable' => $totals['net_payable'],
                'status' => VendorCommissionStatus::Settled->value,
                'return_window_ends_at' => $vendorOrder->delivered_at ? $this->returnWindowEndsAt($vendorOrder) : null,
                'settled_at' => $asOf,
            ]);

            CommissionTransaction::create([
                'vendor_id' => $vendorOrder->vendor_id,
                'order_id' => $vendorOrder->order_id,
                'vendor_order_id' => $vendorOrder->id,
                'vendor_commission_id' => $commission->id,
                'rate' => $totals['commission_rate'],
                'amount' => $totals['commission_amount'],
                'type' => CommissionTransactionType::Charge->value,
                'description' => "Commission on order {$vendorOrder->vendor_order_number}.",
            ]);

            if ($totals['net_payable'] > 0) {
                $this->walletService->creditAvailable(
                    $vendorOrder->vendor,
                    $totals['net_payable'],
                    "Settlement for order {$vendorOrder->vendor_order_number}.",
                    VendorWalletTransactionType::Commission,
                    $commission,
                );
            }

            return $commission;
        });
    }

    /**
     * Settle a vendor order immediately once a prepaid (wallet) payment has
     * been captured, without waiting for delivery or the return window.
     * Idempotent: an already-settled order is returned untouched.
     */
    public function settleOnPrepaidPayment(VendorOrder $vendorOrder): ?VendorCommission
    {
        if ($vendorOrder->vendorCommission()->exists()) {
            return $vendorOrder->vendorCommission()->first();
        }

        return $this->settle($vendorOrder, requireDelivered: false);
    }

    /**
     * Reverse an already settled order (e.g. full refund) and recover funds
     * from the vendor wallet.
     */
    public function reverse(VendorCommission $commission, string $reason): VendorCommission
    {
        if ($commission->status !== VendorCommissionStatus::Settled) {
            throw new RuntimeException(__('Only settled commissions can be reversed.'));
        }

        return DB::transaction(function () use ($commission, $reason): VendorCommission {
            $commission->forceFill([
                'status' => VendorCommissionStatus::Reversed->value,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ])->save();

            CommissionTransaction::create([
                'vendor_id' => $commission->vendor_id,
                'order_id' => $commission->order_id,
                'vendor_order_id' => $commission->vendor_order_id,
                'vendor_commission_id' => $commission->id,
                'rate' => $commission->commission_rate,
                'amount' => $commission->commission_amount,
                'type' => CommissionTransactionType::Reversal->value,
                'description' => $reason,
            ]);

            if ((float) $commission->net_payable > 0) {
                $this->walletService->debit(
                    $commission->vendor,
                    (float) $commission->net_payable,
                    "Reversal: {$reason}.",
                    VendorWalletTransactionType::CommissionReversal,
                    $commission,
                );
            }

            return $commission->fresh();
        });
    }

    /**
     * Recover settlement funds for a completed refund. Reverses the whole
     * commission when the vendor order is fully refunded, otherwise recovers
     * the refunded line's net amount and reduces the outstanding commission.
     */
    public function reverseForRefund(Refund $refund): void
    {
        if ($refund->vendor_order_id) {
            $this->reverseForCancelledVendorOrder($refund);

            return;
        }

        $item = $refund->orderItem;

        if (! $item || ! $item->vendor_order_id) {
            return;
        }

        $vendorOrder = VendorOrder::query()->find($item->vendor_order_id);

        if (! $vendorOrder) {
            return;
        }

        $commission = $vendorOrder->vendorCommission()->first();

        if (! $commission || $commission->status !== VendorCommissionStatus::Settled) {
            return;
        }

        if ($this->isFullyRefunded($vendorOrder)) {
            $this->reverse($commission, "Full refund for order {$vendorOrder->vendor_order_number}.");

            return;
        }

        $this->reverseItemProportionally($commission, $item);
    }

    /**
     * Recover the whole settlement for a cancelled vendor order. Cancellation
     * refunds the entire sub-order, so the commission is reversed in full.
     */
    protected function reverseForCancelledVendorOrder(Refund $refund): void
    {
        $vendorOrder = VendorOrder::query()->find($refund->vendor_order_id);

        if (! $vendorOrder) {
            return;
        }

        $commission = $vendorOrder->vendorCommission()->first();

        if (! $commission || $commission->status !== VendorCommissionStatus::Settled) {
            return;
        }

        $this->reverse($commission, "Cancellation refund for order {$vendorOrder->vendor_order_number}.");
    }

    /**
     * True when every active item on the vendor order has a completed refund.
     */
    public function isFullyRefunded(VendorOrder $vendorOrder): bool
    {
        $activeItems = $vendorOrder->items()
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->get();

        if ($activeItems->isEmpty()) {
            return false;
        }

        $refundedItemIds = Refund::query()
            ->whereIn('order_item_id', $activeItems->pluck('id'))
            ->where('status', RefundStatus::Completed->value)
            ->distinct()
            ->pluck('order_item_id');

        return $activeItems->pluck('id')->diff($refundedItemIds)->isEmpty();
    }

    protected function reverseItemProportionally(VendorCommission $commission, OrderItem $item): void
    {
        $net = min(
            (float) $commission->net_payable,
            round((float) $item->total - (float) $item->commission_amount, 2),
        );

        if ($net <= 0) {
            return;
        }

        DB::transaction(function () use ($commission, $item, $net): void {
            $newPayable = round((float) $commission->net_payable - $net, 2);

            $this->walletService->debit(
                $commission->vendor,
                $net,
                "Partial refund reversal for order item #{$item->id}.",
                VendorWalletTransactionType::CommissionReversal,
                $commission,
            );

            CommissionTransaction::create([
                'vendor_id' => $commission->vendor_id,
                'order_id' => $commission->order_id,
                'vendor_order_id' => $commission->vendor_order_id,
                'vendor_commission_id' => $commission->id,
                'rate' => $commission->commission_rate,
                'amount' => $net,
                'type' => CommissionTransactionType::Reversal->value,
                'description' => "Partial refund reversal for order item #{$item->id}.",
            ]);

            if ($newPayable <= 0) {
                $commission->forceFill([
                    'status' => VendorCommissionStatus::Reversed->value,
                    'reversed_at' => now(),
                    'reversal_reason' => 'Partial refunds exhausted the payable amount.',
                    'eligible_amount' => 0,
                    'commission_amount' => 0,
                    'net_payable' => 0,
                ])->save();

                return;
            }

            $commission->forceFill([
                'eligible_amount' => max(0, round((float) $commission->eligible_amount - (float) $item->total, 2)),
                'commission_amount' => max(0, round((float) $commission->commission_amount - (float) $item->commission_amount, 2)),
                'net_payable' => $newPayable,
            ])->save();
        });
    }

    public function returnWindowEndsAt(VendorOrder $vendorOrder): Carbon
    {
        $windowDays = (int) config('commission.settlement_return_window_days', 7);

        return $vendorOrder->delivered_at->copy()->addDays($windowDays);
    }

    /**
     * Funds that have cleared their delivery wait but are not yet settled.
     */
    public function pendingSettlementAmount(VendorProfile $vendor): float
    {
        $total = 0.0;

        $this->eligibleVendorOrders(now(), $vendor)->each(function (VendorOrder $order) use (&$total): void {
            $total += $this->settlementTotals($order)['net_payable'];
        });

        return round($total, 2);
    }

    public function isEligibleForSettlement(VendorOrder $vendorOrder, ?Carbon $asOf = null): bool
    {
        $asOf = $asOf ?? now();

        if ($vendorOrder->status !== VendorOrderStatus::Delivered->value) {
            return false;
        }

        if (! $vendorOrder->delivered_at) {
            return false;
        }

        if ($this->returnWindowEndsAt($vendorOrder)->greaterThan($asOf)) {
            return false;
        }

        if ($vendorOrder->vendorCommission()->exists()) {
            return false;
        }

        return ! $this->hasOpenReturn($vendorOrder);
    }

    /**
     * @return Collection<int, VendorOrder>
     */
    public function eligibleVendorOrders(?Carbon $asOf = null, ?VendorProfile $vendor = null): Collection
    {
        $asOf = $asOf ?? now();
        $windowDays = (int) config('commission.settlement_return_window_days', 7);

        return VendorOrder::query()
            ->where('status', VendorOrderStatus::Delivered->value)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', $asOf->copy()->subDays($windowDays))
            ->when($vendor, function ($query) use ($vendor): void {
                $query->where('vendor_id', $vendor->id);
            })
            ->whereDoesntHave('vendorCommission')
            ->get()
            ->filter(fn (VendorOrder $order): bool => ! $this->hasOpenReturn($order))
            ->values();
    }

    /**
     * @return array{eligible_amount: float, discount_amount: float, commission_rate: float, commission_amount: float, net_payable: float}
     */
    public function settlementTotals(VendorOrder $vendorOrder): array
    {
        $items = $vendorOrder->items()
            ->whereNot('status', OrderStatus::Cancelled->value)
            ->get();

        $eligible = (float) $items->sum(fn ($item): float => (float) $item->total);
        $commission = (float) $items->sum(fn ($item): float => (float) $item->commission_amount);
        $itemCount = max($items->count(), 1);
        $averageRate = $items->count()
            ? round($items->sum(fn ($item): float => (float) $item->commission_rate) / $itemCount, 2)
            : 0;

        $discount = max(0.0, round((float) $vendorOrder->discount, 2));

        return [
            'eligible_amount' => round($eligible, 2),
            'discount_amount' => $discount,
            'commission_rate' => $averageRate,
            'commission_amount' => round($commission, 2),
            'net_payable' => round($eligible - $commission, 2),
        ];
    }

    protected function hasOpenReturn(VendorOrder $vendorOrder): bool
    {
        $itemIds = $vendorOrder->items()->pluck('id');

        if ($itemIds->isEmpty()) {
            return false;
        }

        return ProductReturn::query()
            ->whereIn('order_item_id', $itemIds)
            ->whereNotIn('status', [ReturnStatus::Refunded->value, ReturnStatus::Rejected->value])
            ->exists();
    }
}
