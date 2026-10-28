<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\WalletHoldStatus;
use App\Enums\WalletTransactionType;
use App\Events\PaymentSucceeded;
use App\Events\RefundCompleted;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhook;
use App\Models\Refund;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Inventory\InventoryService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\VendorSettlementService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected RazorpayService $razorpay,
        protected InventoryService $inventoryService,
        protected WalletService $walletService,
        protected PaymentSettingsService $settings,
        protected OrderStatusService $orderStatus,
        protected VendorSettlementService $settlements,
    ) {}

    /** @return array{order: Order, razorpay: array<string, mixed>|null} */
    public function createOrderPayment(Order $order, string $method = 'razorpay'): array
    {
        $method = $order->payment_method ?: $method;

        if (! PaymentMethod::tryFrom($method)) {
            throw new \InvalidArgumentException('Unsupported payment method.');
        }

        if (! $this->settings->isMethodEnabled($method)) {
            throw new \InvalidArgumentException('This payment method is currently unavailable.');
        }

        return match (PaymentMethod::from($method)) {
            PaymentMethod::Wallet => $this->createWalletPayment($order),
            PaymentMethod::Cod => $this->createCodPayment($order),
            PaymentMethod::Razorpay => $this->createGatewayPayment($order),
        };
    }

    protected function createWalletPayment(Order $order): array
    {
        $pending = null;

        DB::transaction(function () use ($order, &$pending): void {
            $this->captureWalletHold($order);

            $order->forceFill([
                'payment_status' => PaymentStatus::Paid->value,
                'order_status' => OrderStatus::Confirmed->value,
                'paid_at' => now(),
            ])->save();

            $this->orderStatus->confirmAllPendingVendorOrders($order);

            foreach ($order->vendorOrders()->get() as $vendorOrder) {
                $this->settlements->settleOnPrepaidPayment($vendorOrder);
            }

            $order->payments()->create([
                'user_id' => $order->user_id,
                'amount' => $order->total,
                'method' => PaymentMethod::Wallet->value,
                'payment_method' => PaymentMethod::Wallet->value,
                'status' => PaymentStatus::Paid->value,
                'gateway' => 'wallet',
                'currency' => 'INR',
                'metadata' => ['source' => 'wallet'],
            ]);

            foreach ($order->items as $item) {
                if ($item->product) {
                    $variant = $item->product_variant_id ? $item->variant : null;
                    $this->inventoryService->sold($item->product, $variant, $item->quantity, $order, $order->user_id);
                }
            }

            $pending = $order->fresh();
        });

        if ($pending) {
            PaymentSucceeded::dispatch($pending);
        }

        return [
            'order' => $pending ?? $order->fresh(),
            'razorpay' => null,
        ];
    }

    protected function createCodPayment(Order $order): array
    {
        if ((float) $order->wallet_amount > 0) {
            throw new \InvalidArgumentException('Wallet balance cannot be combined with Cash on Delivery.');
        }

        DB::transaction(function () use ($order): void {
            $order->forceFill([
                'payment_status' => PaymentStatus::CodPending->value,
            ])->save();

            $order->payments()->create([
                'user_id' => $order->user_id,
                'amount' => $order->total,
                'method' => PaymentMethod::Cod->value,
                'payment_method' => PaymentMethod::Cod->value,
                'status' => PaymentStatus::Pending->value,
                'gateway' => 'cod',
                'currency' => 'INR',
                'metadata' => ['flow' => 'cod_pending'],
            ]);
        });

        return [
            'order' => $order->fresh(),
            'razorpay' => null,
        ];
    }

    protected function createGatewayPayment(Order $order): array
    {
        $existing = $order->payments()
            ->where('gateway', 'razorpay')
            ->where('status', PaymentStatus::Pending->value)
            ->latest('id')
            ->first();

        if ($existing?->gateway_order_id) {
            return $this->gatewayCheckoutPayload($order, $existing, $existing->amount);
        }

        $razorpayOrder = $this->razorpay->createOrder([
            'amount' => (float) $order->total,
            'currency' => 'INR',
            'receipt' => $order->order_number,
            'notes' => [
                'order_number' => $order->order_number,
                'wallet_amount' => (float) $order->wallet_amount,
            ],
        ]);

        DB::transaction(function () use ($order, $razorpayOrder): void {
            $payment = $order->payments()->create([
                'user_id' => $order->user_id,
                'amount' => $order->total,
                'method' => PaymentMethod::Razorpay->value,
                'payment_method' => PaymentMethod::Razorpay->value,
                'status' => PaymentStatus::Pending->value,
                'gateway' => 'razorpay',
                'gateway_order_id' => $razorpayOrder['id'],
                'currency' => $razorpayOrder['currency'] ?? 'INR',
                'metadata' => $razorpayOrder,
                'payload' => $razorpayOrder,
            ]);

            $this->recordTransaction($payment, 'ORDER_CREATED', (float) $order->total, null, $razorpayOrder['id'], $razorpayOrder);
        });

        return $this->gatewayCheckoutPayload($order, $order->payments()->latest('id')->first(), $order->total);
    }

    /** @return array{order: Order, razorpay: array<string, mixed>|null} */
    protected function gatewayCheckoutPayload(Order $order, Payment $payment, float $amount): array
    {
        return [
            'order' => $order->fresh(),
            'razorpay' => [
                'order_id' => $payment->gateway_order_id,
                'payment_id' => (int) $payment->id,
                'amount' => (int) round($amount * 100),
                'currency' => $payment->currency ?? 'INR',
                'key' => $this->razorpay->keyId(),
                'wallet_amount' => (float) $order->wallet_amount,
            ],
        ];
    }

    public function verifyAndCapture(Order $order, array $payload): bool
    {
        if ($order->payment_status === PaymentStatus::CodPending->value) {
            return false;
        }

        $payment = $order->payments()
            ->where('gateway', 'razorpay')
            ->where('gateway_order_id', $payload['razorpay_order_id'])
            ->latest('id')
            ->first();

        $paymentId = $payload['razorpay_payment_id'] ?? null;
        $signature = $payload['razorpay_signature'] ?? null;

        if ($paymentId === null || $signature === null) {
            return false;
        }

        if (! $this->razorpay->verifyPayment($payload['razorpay_order_id'], $paymentId, $signature)) {
            return false;
        }

        try {
            $captured = $this->razorpay->capturePayment($paymentId, (float) $order->total);
        } catch (\Throwable) {
            try {
                $fetched = $this->razorpay->fetchPayment($paymentId);
                $status = $fetched['status'] ?? null;
                if (! in_array($status, ['captured', 'authorized'], true)) {
                    return false;
                }
                $captured = $fetched;
            } catch (\Throwable) {
                return false;
            }
        }

        // Idempotency guard: if this payment was already captured and
        // finalized, do not run the side effects again.
        if ($payment && $payment->status === PaymentStatus::Paid->value) {
            return true;
        }

        $success = DB::transaction(function () use ($order, $captured, $paymentId, $payment): bool {
            if ($payment && $payment->status === PaymentStatus::Paid->value) {
                return true;
            }

            if ($order->payment_status !== PaymentStatus::Paid->value) {
                $order->forceFill([
                    'razorpay_payment_id' => $paymentId,
                    'payment_status' => PaymentStatus::Paid->value,
                    'order_status' => OrderStatus::Confirmed->value,
                    'paid_at' => now(),
                ])->save();
            }

            $this->orderStatus->confirmAllPendingVendorOrders($order);

            $record = $payment ?? $order->payments()->latest('id')->first();

            if ($record) {
                $record->forceFill([
                    'user_id' => $order->user_id,
                    'amount' => $order->total,
                    'method' => PaymentMethod::Razorpay->value,
                    'payment_method' => PaymentMethod::Razorpay->value,
                    'status' => PaymentStatus::Paid->value,
                    'gateway' => 'razorpay',
                    'transaction_id' => $paymentId,
                    'gateway_payment_id' => $paymentId,
                    'currency' => 'INR',
                    'metadata' => $captured,
                    'payload' => $captured,
                ])->save();

                $this->recordTransaction($record, 'CAPTURED', (float) $order->total, null, $paymentId, $captured);
            }

            foreach ($order->items as $item) {
                if ($item->product) {
                    $variant = $item->product_variant_id ? $item->variant : null;
                    $this->inventoryService->sold($item->product, $variant, $item->quantity, $order, $order->user_id);
                }
            }

            $this->captureWalletHold($order);

            return true;
        });

        if ($success) {
            PaymentSucceeded::dispatch($order->fresh());
        }

        return $success;
    }

    public function markFailed(Order $order, ?string $reason = null): void
    {
        DB::transaction(function () use ($order, $reason): void {
            $order->forceFill(['payment_status' => PaymentStatus::Failed->value])->save();

            $payment = $order->payments()->create([
                'user_id' => $order->user_id,
                'amount' => $order->total,
                'method' => $order->payment_method ?: PaymentMethod::Razorpay->value,
                'payment_method' => $order->payment_method ?: PaymentMethod::Razorpay->value,
                'status' => PaymentStatus::Failed->value,
                'gateway' => 'razorpay',
                'currency' => 'INR',
                'metadata' => $reason ? ['error' => $reason] : [],
                'payload' => $reason ? ['error' => $reason] : [],
            ]);

            $this->recordTransaction($payment, 'FAILED', (float) $order->total, 'FAILED', null, null, $reason);

            $this->releaseWalletHold($order, $reason);
        });
    }

    public function createRechargePayment(User $user, float $amount): array
    {
        if (! $this->razorpay->hasCredentials()) {
            throw new \RuntimeException('Razorpay is not configured. Please add your Test/Live API keys in Payment Settings.');
        }

        $wallet = $this->walletService->walletFor($user);

        $razorpayOrder = $this->razorpay->createOrder([
            'amount' => $amount,
            'currency' => 'INR',
            'receipt' => 'WAL-'.strtoupper(uniqid()),
            'notes' => ['type' => 'wallet_recharge'],
        ]);

        DB::transaction(function () use ($user, $wallet, $razorpayOrder, $amount): void {
            $payment = Payment::create([
                'user_id' => $user->id,
                'order_id' => null,
                'amount' => $amount,
                'method' => PaymentMethod::Razorpay->value,
                'payment_method' => PaymentMethod::Razorpay->value,
                'status' => PaymentStatus::Pending->value,
                'gateway' => 'razorpay',
                'gateway_order_id' => $razorpayOrder['id'],
                'currency' => $razorpayOrder['currency'] ?? 'INR',
                'metadata' => $razorpayOrder,
                'reference_type' => Wallet::class,
                'reference_id' => $wallet->id,
                'payload' => $razorpayOrder,
            ]);

            $this->recordTransaction($payment, 'ORDER_CREATED', $amount, null, $razorpayOrder['id'], $razorpayOrder);
        });

        return [
            'razorpay' => [
                'order_id' => $razorpayOrder['id'],
                'amount' => $razorpayOrder['amount'],
                'currency' => $razorpayOrder['currency'],
                'key' => $this->razorpay->keyId(),
            ],
        ];
    }

    public function verifyAndCaptureRecharge(Payment $payment, array $payload): bool
    {
        if ($payment->status === PaymentStatus::Paid->value) {
            return true;
        }

        $valid = $this->razorpay->verifyPayment(
            $payload['razorpay_order_id'],
            $payload['razorpay_payment_id'],
            $payload['razorpay_signature'],
        );

        if (! $valid || ($payload['razorpay_order_id'] ?? null) !== ($payment->gateway_order_id ?: ($payment->payload['id'] ?? null))) {
            return false;
        }

        $paymentId = $payload['razorpay_payment_id'];

        try {
            $captured = $this->razorpay->capturePayment($paymentId, (float) $payment->amount);
        } catch (\Throwable) {
            try {
                $fetched = $this->razorpay->fetchPayment($paymentId);
                $status = $fetched['status'] ?? null;
                if (! in_array($status, ['captured', 'authorized'], true)) {
                    return false;
                }
                $captured = $fetched;
            } catch (\Throwable) {
                return false;
            }
        }

        DB::transaction(function () use ($payment, $captured, $paymentId): void {
            $payment->forceFill([
                'status' => PaymentStatus::Paid->value,
                'transaction_id' => $paymentId,
                'gateway_payment_id' => $paymentId,
                'metadata' => $captured,
                'payload' => $captured,
            ])->save();

            $this->recordTransaction($payment, 'CAPTURED', (float) $payment->amount, null, $paymentId, $captured);

            $wallet = $payment->reference;
            $this->walletService->recharge(
                $wallet->user,
                (float) $payment->amount,
                'Money added to wallet.',
                $payment,
            );
        });

        return true;
    }

    public function handleWebhook(array $event, string $rawPayload, string $signature): PaymentWebhook
    {
        $webhookEvent = $event['event'] ?? null;

        if (! is_string($webhookEvent)) {
            throw new \InvalidArgumentException('Invalid webhook event.');
        }

        if (! $this->razorpay->verifyWebhookSignature($rawPayload, $signature)) {
            throw new \InvalidArgumentException('Invalid webhook signature.');
        }

        $webhook = PaymentWebhook::create([
            'event' => $webhookEvent,
            'gateway' => 'razorpay',
            'gateway_order_id' => $this->gatewayOrderIdFromEvent($event),
            'gateway_payment_id' => $this->gatewayPaymentIdFromEvent($event),
            'payload' => $event,
            'signature' => $signature,
            'source_ip' => request()->ip(),
            'verified' => true,
            'status' => 'RECEIVED',
        ]);

        try {
            $status = $this->processWebhookEvent($event);
            $webhook->forceFill(['status' => $status, 'processed_at' => now()])->save();

            return $webhook;
        } catch (\Throwable $e) {
            logger()->error('Razorpay webhook processing failed', [
                'event' => $webhookEvent,
                'message' => $e->getMessage(),
            ]);

            $webhook->forceFill(['status' => 'FAILED', 'processed_at' => now()])->save();

            throw $e;
        }
    }

    protected function processWebhookEvent(array $event): string
    {
        return match ($event['event'] ?? '') {
            'payment.captured' => $this->handlePaymentCaptured($event),
            'payment.failed' => $this->handlePaymentFailed($event),
            'order.paid' => $this->handleOrderPaid($event),
            'refund.processed', 'refund.created' => $this->handleRefundProcessed($event),
            default => 'IGNORED',
        };
    }

    protected function handlePaymentCaptured(array $event): string
    {
        $entity = $event['payload']['payment']['entity'] ?? null;
        $paymentId = $entity['id'] ?? null;

        $payment = $paymentId ? Payment::where('gateway_payment_id', $paymentId)->latest('id')->first() : null;

        if (! $payment) {
            $orderId = $entity['order_id'] ?? null;
            $payment = $orderId ? Payment::where('gateway_order_id', $orderId)->latest('id')->first() : null;
        }

        if (! $payment) {
            return 'SKIPPED_NO_MATCH';
        }

        $this->finalizePayment($payment, $paymentId ?? $entity['id'] ?? null, $entity);

        return 'PROCESSED';
    }

    protected function handlePaymentFailed(array $event): string
    {
        $entity = $event['payload']['payment']['entity'] ?? null;
        $paymentId = $entity['id'] ?? null;

        $payment = $paymentId ? Payment::where('gateway_payment_id', $paymentId)->latest('id')->first() : null;

        if (! $payment) {
            $orderId = $entity['order_id'] ?? null;
            $payment = $orderId ? Payment::where('gateway_order_id', $orderId)->latest('id')->first() : null;
        }

        if (! $payment || $payment->status === PaymentStatus::Failed->value || $payment->status === PaymentStatus::Paid->value) {
            return $payment ? 'IGNORED' : 'SKIPPED_NO_MATCH';
        }

        $payment->forceFill(['status' => PaymentStatus::Failed->value])->save();

        $this->recordTransaction($payment, 'FAILED', (float) $payment->amount, 'FAILED', $paymentId, $entity, $entity['error_description'] ?? null);

        $order = $payment->order;
        if ($order && $order->payment_status !== PaymentStatus::Failed->value) {
            $order->forceFill(['payment_status' => PaymentStatus::Failed->value])->save();
            $this->releaseWalletHold($order, $entity['error_description'] ?? __('Payment failed.'));
        }

        return 'PROCESSED';
    }

    protected function handleOrderPaid(array $event): string
    {
        $entity = $event['payload']['order']['entity'] ?? null;
        $orderId = $entity['id'] ?? null;

        $payment = $orderId ? Payment::where('gateway_order_id', $orderId)->latest('id')->first() : null;

        if (! $payment) {
            return 'SKIPPED_NO_MATCH';
        }

        if ($payment->status === PaymentStatus::Paid->value) {
            return 'IGNORED';
        }

        try {
            $captured = $this->razorpay->fetchPayment($payment->gateway_payment_id ?? '');
        } catch (\Throwable) {
            return 'DEFERRED';
        }

        $this->finalizePayment($payment, $payment->gateway_payment_id, $captured);

        return 'PROCESSED';
    }

    protected function handleRefundProcessed(array $event): string
    {
        $entity = $event['payload']['refund']['entity'] ?? null;
        $gatewayRefundId = $entity['id'] ?? null;
        $paymentId = $entity['payment_id'] ?? null;

        $refund = null;
        if ($gatewayRefundId) {
            $refund = Refund::where('razorpay_refund_id', $gatewayRefundId)->latest('id')->first();
        }
        if (! $refund && $paymentId) {
            $payment = Payment::where('gateway_payment_id', $paymentId)->latest('id')->first();
            $refund = $payment?->order?->refunds()->latest('id')->first();
        }

        if (! $refund) {
            return 'SKIPPED_NO_MATCH';
        }

        if ($refund->status !== RefundStatus::Completed->value) {
            $refund->forceFill([
                'status' => RefundStatus::Completed->value,
                'razorpay_refund_id' => $gatewayRefundId ?: $refund->razorpay_refund_id,
                'processed_at' => now(),
            ])->save();

            if ($refund->order && $refund->order->payment_method === PaymentMethod::Wallet->value) {
                $this->walletService->credit(
                    $refund->user,
                    (float) $refund->amount,
                    'Refund for order '.$refund->order->order_number,
                    WalletTransactionType::Refund,
                    $refund,
                );
            }

            RefundCompleted::dispatch($refund);
        }

        return 'PROCESSED';
    }

    public function finalizePayment(Payment $payment, ?string $paymentId, array $captured): void
    {
        $order = $payment->order;

        if (! $order) {
            $payment->forceFill(['status' => PaymentStatus::Paid->value, 'gateway_payment_id' => $paymentId ?: $payment->gateway_payment_id])->save();

            return;
        }

        // Idempotency guard: a payment marked as paid has already been
        // finalized. Rerunning it (e.g. a retried webhook) must not re-deduct
        // inventory, create duplicate transactions, or re-dispatch the event.
        if ($payment->status === PaymentStatus::Paid->value) {
            return;
        }

        DB::transaction(function () use ($order, $payment, $paymentId, $captured): void {
            if ($payment->status === PaymentStatus::Paid->value) {
                return;
            }

            if ($order->payment_status !== PaymentStatus::Paid->value) {
                $order->forceFill([
                    'razorpay_payment_id' => $paymentId ?: $order->razorpay_payment_id,
                    'payment_status' => PaymentStatus::Paid->value,
                    'order_status' => OrderStatus::Confirmed->value,
                    'paid_at' => now(),
                ])->save();
            }

            $this->orderStatus->confirmAllPendingVendorOrders($order);

            $payment->forceFill([
                'status' => PaymentStatus::Paid->value,
                'transaction_id' => $paymentId ?: $payment->transaction_id,
                'gateway_payment_id' => $paymentId ?: $payment->gateway_payment_id,
                'metadata' => $captured,
                'payload' => $captured,
            ])->save();

            $this->recordTransaction($payment, 'CAPTURED', (float) $payment->amount, null, $paymentId, $captured);

            foreach ($order->items as $item) {
                if ($item->product) {
                    $variant = $item->product_variant_id ? $item->variant : null;
                    $this->inventoryService->sold($item->product, $variant, $item->quantity, $order, $order->user_id);
                }
            }

            $this->captureWalletHold($order);
        });

        PaymentSucceeded::dispatch($order->fresh());
    }

    protected function recordTransaction(
        Payment $payment,
        string $type,
        float $amount,
        ?string $status = null,
        ?string $gatewayTxnId = null,
        ?array $payload = null,
        ?string $notes = null,
    ): PaymentTransaction {
        return $payment->transactions()->create([
            'type' => $type,
            'status' => $status ?? 'SUCCESS',
            'amount' => $amount,
            'gateway_txn_id' => $gatewayTxnId,
            'payload' => $payload,
            'notes' => $notes,
        ]);
    }

    protected function gatewayOrderIdFromEvent(array $event): ?string
    {
        foreach (['payment', 'order'] as $key) {
            $entity = $event['payload'][$key]['entity'] ?? null;
            if (isset($entity['order_id'])) {
                return $entity['order_id'];
            }
            if ($key === 'order' && isset($entity['id'])) {
                return $entity['id'];
            }
        }

        return null;
    }

    protected function gatewayPaymentIdFromEvent(array $event): ?string
    {
        foreach (['payment', 'refund'] as $key) {
            $entity = $event['payload'][$key]['entity'] ?? null;
            if (isset($entity['id']) && $key === 'payment') {
                return $entity['id'];
            }
            if (isset($entity['payment_id']) && $key === 'refund') {
                return $entity['payment_id'];
            }
        }

        return null;
    }

    protected function captureWalletHold(Order $order): void
    {
        $hold = $order->walletHolds()->where('status', WalletHoldStatus::Pending->value)->latest('id')->first();

        if ($hold) {
            $this->walletService->captureHold($hold);
        }
    }

    protected function releaseWalletHold(Order $order, ?string $reason): void
    {
        $hold = $order->walletHolds()->where('status', WalletHoldStatus::Pending->value)->latest('id')->first();

        if ($hold) {
            $this->walletService->releaseHold($hold, $reason);
        }
    }
}
