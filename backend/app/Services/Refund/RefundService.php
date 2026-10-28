<?php

namespace App\Services\Refund;

use App\Enums\InventoryTransactionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnStatus;
use App\Enums\WalletHoldStatus;
use App\Enums\WalletTransactionType;
use App\Events\RefundCompleted;
use App\Models\Order;
use App\Models\ProductReturn;
use App\Models\Refund;
use App\Models\VendorOrder;
use App\Services\Inventory\InventoryService;
use App\Services\Ledger\AdminLedgerService;
use App\Services\Payment\RazorpayService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function __construct(
        protected RazorpayService $razorpay,
        protected WalletService $walletService,
        protected InventoryService $inventoryService,
        protected AdminLedgerService $ledgerService,
    ) {}

    /**
     * Create (or re-open) the PENDING refund for a return. The amount is the
     * shelf value of the returned quantity and is capped so the aggregate of
     * committed refunds never exceeds what the buyer paid.
     */
    public function initiateForReturn(ProductReturn $productReturn): Refund
    {
        return DB::transaction(function () use ($productReturn): Refund {
            if ($productReturn->refunds()->where('status', RefundStatus::Completed->value)->exists()) {
                throw new \InvalidArgumentException('A refund has already been completed for this return.');
            }

            $item = $productReturn->orderItem;
            $order = $productReturn->order;

            $itemsTotal = (float) $order->items()->sum('total');
            $scale = $itemsTotal > 0 ? (float) $order->total / $itemsTotal : 1.0;
            $itemPaidValue = round((float) $item->total * $scale, 2);
            $amount = round($itemPaidValue / (int) $item->quantity * (int) $productReturn->quantity, 2);
            $alreadyCommitted = (float) $order->refunds()->sum('amount');
            $cap = max(0.0, (float) $order->total - $alreadyCommitted);

            if ($amount <= 0 || $amount > $cap) {
                throw new \InvalidArgumentException('Refund amount exceeds the order refund limit.');
            }

            $refund = Refund::query()->firstOrCreate(
                ['product_return_id' => $productReturn->id],
                [
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'user_id' => $productReturn->user_id,
                    'amount' => $amount,
                    'quantity' => (int) $productReturn->quantity,
                    'status' => RefundStatus::Pending->value,
                    'reason' => $productReturn->reason,
                ],
            );

            return $refund;
        });
    }

    public function process(Refund $refund): Refund
    {
        return DB::transaction(function () use ($refund): Refund {
            if ($refund->status === RefundStatus::Completed->value) {
                return $refund->refresh();
            }

            $order = $refund->order;

            if (! $order || $order->payment_status !== PaymentStatus::Paid->value) {
                throw new \Exception('Order payment is not eligible for refund.');
            }

            [$walletShare, $gatewayShare, $useGateway] = $refund->vendor_order_id
                ? $this->cancellationSourceSplit($order, $refund)
                : $this->paymentSourceSplit($order, (float) $refund->amount);

            [$method, $walletShare, $gatewayShare] = $this->moveRefundMoney($refund, $walletShare, $gatewayShare, $useGateway);

            $refund->forceFill([
                'status' => RefundStatus::Completed->value,
                'method' => $method,
                'wallet_amount' => $walletShare,
                'gateway_amount' => $gatewayShare,
                'processed_at' => now(),
            ])->save();

            if (! $refund->vendor_order_id) {
                $this->restockReturnedQuantity($refund);
                $this->updateOrderAfterRefund($order);
                $this->markReturnRefunded($refund);
            }

            $this->ledgerService->recordRefund($refund);

            RefundCompleted::dispatch($refund);

            return $refund->refresh();
        });
    }

    /**
     * Create the PENDING refund that returns a cancelled vendor order's paid
     * value to the customer. A vendor order is refunded at most once.
     */
    public function initiateForVendorOrderCancellation(VendorOrder $vendorOrder, string $reason = ''): ?Refund
    {
        $order = $vendorOrder->order;

        if (! $order || $order->payment_status !== PaymentStatus::Paid->value) {
            return null;
        }

        $walletAmount = round((float) $vendorOrder->wallet_amount, 2);
        $gatewayAmount = round((float) $vendorOrder->total, 2);

        // A pending order-level hold is already returned to the wallet when the
        // order is cancelled, so it must not be credited again by the refund.
        if ($order->walletHolds()->where('status', WalletHoldStatus::Pending->value)->exists()) {
            $walletAmount = 0.0;
        }

        $amount = round($walletAmount + $gatewayAmount, 2);

        if ($amount <= 0) {
            return null;
        }

        return Refund::query()->firstOrCreate(
            ['vendor_order_id' => $vendorOrder->id],
            [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'amount' => $amount,
                'wallet_amount' => $walletAmount,
                'gateway_amount' => $gatewayAmount,
                'quantity' => 0,
                'status' => RefundStatus::Pending->value,
                'reason' => $reason ?: 'Order cancelled.',
            ],
        );
    }

    /**
     * Refund a cancelled vendor order to the customer immediately.
     */
    public function refundVendorOrderCancellation(VendorOrder $vendorOrder, string $reason = ''): ?Refund
    {
        $refund = $this->initiateForVendorOrderCancellation($vendorOrder, $reason);

        return $refund ? $this->process($refund) : null;
    }

    /**
     * Decide which payment source(s) should return this refund. When the
     * order was paid partly from the wallet and partly through the gateway,
     * the refund is split in the same proportion so the correct source is
     * always credited back.
     *
     * @return array{0: float, 1: float, 2: bool}
     */
    protected function paymentSourceSplit(Order $order, float $amount): array
    {
        $total = (float) $order->total;

        if ($total <= 0) {
            return [0.0, 0.0, false];
        }

        $useGateway = ! empty($order->razorpay_payment_id)
            && $order->payment_method !== 'cod';

        if (! $useGateway) {
            return [$amount, 0.0, false];
        }

        $walletProportion = min(1.0, (float) $order->wallet_amount / $total);

        $walletShare = round($amount * $walletProportion, 2);
        $gatewayShare = round($amount - $walletShare, 2);

        return [$walletShare, $gatewayShare, true];
    }

    /**
     * Resolve the wallet and gateway portions of a cancellation refund. Each
     * source is refunded back to itself: the vendor order's wallet allocation
     * returns to the wallet and its gateway allocation is refunded through the
     * original payment method.
     *
     * @return array{0: float, 1: float, 2: bool}
     */
    protected function cancellationSourceSplit(Order $order, Refund $refund): array
    {
        $walletShare = round((float) $refund->wallet_amount, 2);
        $gatewayShare = round((float) $refund->gateway_amount, 2);

        $useGateway = $gatewayShare > 0 && ! empty($order->razorpay_payment_id);

        return [$walletShare, $gatewayShare, $useGateway];
    }

    /**
     * Credit the wallet portion back to the customer and push the gateway
     * portion through Razorpay. When the gateway is unavailable or the call
     * fails, the gateway portion falls back to the wallet so the customer is
     * always made whole.
     *
     * @return array{0: string, 1: float, 2: float}
     */
    protected function moveRefundMoney(Refund $refund, float $walletShare, float $gatewayShare, bool $useGateway): array
    {
        $order = $refund->order;

        if (! $useGateway || $gatewayShare <= 0) {
            $walletShare = round($walletShare + $gatewayShare, 2);
            $gatewayShare = 0.0;
        } else {
            try {
                $result = $this->razorpay->refundPayment(
                    $order->razorpay_payment_id,
                    $gatewayShare,
                    ['order_number' => $order->order_number],
                );

                $refund->forceFill(['razorpay_refund_id' => $result['id'] ?? null])->save();
            } catch (\Throwable) {
                $walletShare = round($walletShare + $gatewayShare, 2);
                $gatewayShare = 0.0;
            }
        }

        if ($walletShare > 0) {
            $this->walletService->credit(
                $refund->user,
                $walletShare,
                'Refund for order '.$order->order_number,
                WalletTransactionType::Refund,
                $refund,
            );
        }

        $method = match (true) {
            $gatewayShare > 0 && $walletShare > 0 => 'SPLIT',
            $gatewayShare > 0 => 'GATEWAY',
            default => 'WALLET',
        };

        return [$method, $walletShare, $gatewayShare];
    }

    protected function restockReturnedQuantity(Refund $refund): void
    {
        $item = $refund->orderItem;

        if (! $item?->product) {
            return;
        }

        $this->inventoryService->adjust(
            $item->product,
            $item->product_variant_id ? $item->variant : null,
            InventoryTransactionType::Return->value,
            (int) $refund->quantity,
            'Stock returned after refund.',
            $refund->order,
            $refund->user_id,
        );
    }

    protected function markReturnRefunded(Refund $refund): void
    {
        $productReturn = $refund->productReturn;

        if (! $productReturn) {
            return;
        }

        $productReturn->forceFill([
            'status' => ReturnStatus::Refunded->value,
            'status_notes' => 'Refund processed.',
            'refunded_at' => now(),
        ])->save();
    }

    protected function updateOrderAfterRefund(Order $order): void
    {
        $order->refresh();

        $totalCompleted = (float) $order->refunds()
            ->where('status', RefundStatus::Completed->value)
            ->sum('amount');

        if ($totalCompleted >= (float) $order->total) {
            $order->forceFill([
                'order_status' => OrderStatus::Refunded->value,
                'payment_status' => PaymentStatus::Refunded->value,
            ])->save();
        } elseif ($order->payment_status === PaymentStatus::Paid->value) {
            $order->forceFill(['payment_status' => PaymentStatus::PartiallyRefunded->value])->save();
        }
    }
}
