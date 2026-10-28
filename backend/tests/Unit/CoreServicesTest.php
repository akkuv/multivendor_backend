<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Inventory\InventoryService;
use App\Services\Payment\PaymentService;
use App\Services\Refund\RefundService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreServicesTest extends TestCase
{
    use RefreshDatabase;

    private function customerWallet(): array
    {
        $user = User::factory()->customer()->create();
        $wallet = $user->wallet()->firstOrCreate(['user_id' => $user->id], ['available_balance' => 0]);

        return [$user, $wallet];
    }

    public function test_wallet_credit_is_idempotent_for_the_same_reference(): void
    {
        [$user, $wallet] = $this->customerWallet();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $service = app(WalletService::class);

        $service->credit($user, 100, 'Recharge', WalletTransactionType::Recharge, $order);
        $service->credit($user, 100, 'Recharge', WalletTransactionType::Recharge, $order);

        $this->assertSame(100.0, (float) $wallet->fresh()->available_balance);
        $this->assertSame(1, WalletTransaction::where('wallet_id', $wallet->id)
            ->where('type', WalletTransactionType::Recharge->value)
            ->where('reference_id', $order->id)
            ->where('status', WalletTransactionStatus::Completed->value)
            ->count());
    }

    public function test_wallet_debit_is_idempotent_and_rejects_insufficient_funds(): void
    {
        [$user, $wallet] = $this->customerWallet();
        $wallet->update(['available_balance' => 200]);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $service = app(WalletService::class);

        $service->debit($user, 100, 'Pay', WalletTransactionType::Debit, $order);
        $service->debit($user, 100, 'Pay', WalletTransactionType::Debit, $order);

        $this->assertSame(100.0, (float) $wallet->fresh()->available_balance);

        $this->expectException(\RuntimeException::class);
        $service->debit($user, 500, 'Pay', WalletTransactionType::Debit, Order::factory()->create());
    }

    public function test_refund_process_is_idempotent(): void
    {
        $user = User::factory()->customer()->create();
        $wallet = $user->wallet()->firstOrCreate(['user_id' => $user->id], ['available_balance' => 0]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'payment_status' => PaymentStatus::Paid->value,
            'payment_method' => 'wallet',
            'total' => 500,
        ]);

        $refund = Refund::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => 200,
            'quantity' => 1,
            'status' => RefundStatus::Pending->value,
        ]);

        $service = app(RefundService::class);
        app(Product::class); // ensure catalog model is loaded

        $service->process($refund);
        $service->process($refund->fresh());

        $this->assertSame(200.0, (float) $wallet->fresh()->available_balance);
        $this->assertSame(1, WalletTransaction::where('wallet_id', $wallet->id)
            ->where('type', WalletTransactionType::Refund->value)
            ->where('reference_id', $refund->id)
            ->count());
        $this->assertSame(RefundStatus::Completed->value, $refund->fresh()->status);
    }

    public function test_inventory_sold_decrements_and_prevents_overselling(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $service = app(InventoryService::class);
        $order = Order::factory()->create();

        $service->sold($product, null, 2, $order);

        $this->assertSame(1, (int) $product->fresh()->stock);

        $this->expectException(InsufficientStockException::class);
        $service->sold($product, null, 2, $order);
    }

    public function test_payment_finalize_is_idempotent_for_inventory_and_transactions(): void
    {
        $user = User::factory()->customer()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total' => 300,
            'payment_method' => 'razorpay',
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 300,
            'total' => 300,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => 300,
            'status' => PaymentStatus::Pending->value,
            'gateway' => 'razorpay',
        ]);

        $service = app(PaymentService::class);

        $service->finalizePayment($payment, 'pay_abc123', ['id' => 'pay_abc123']);
        $service->finalizePayment($payment->fresh(), 'pay_abc123', ['id' => 'pay_abc123']);

        $this->assertSame(4, (int) $product->fresh()->stock);
        $this->assertSame(PaymentStatus::Paid->value, $payment->fresh()->status);
        $this->assertSame(1, PaymentTransaction::where('payment_id', $payment->id)
            ->where('type', 'CAPTURED')
            ->count());

        $this->assertNotNull($item->refresh());
    }
}
