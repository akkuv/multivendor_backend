<?php

namespace Tests\Feature;

use App\Enums\VendorCommissionStatus;
use App\Enums\WalletHoldStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Events\OrderPlaced;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Payment\PaymentService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([OrderPlaced::class]);

        Http::fake([
            'api.razorpay.com/*' => Http::response([
                'id' => 'order_rzp_test',
                'amount' => 1,
                'currency' => 'INR',
                'receipt' => 'WAL-TEST',
            ], 200),
        ]);
    }

    protected function service(): WalletService
    {
        return app(WalletService::class);
    }

    protected function addressFor(User $user): Address
    {
        return Address::factory()->create(['user_id' => $user->id]);
    }

    protected function placeOrder(User $user, array $payload = []): TestResponse
    {
        Sanctum::actingAs($user);

        return $this->postJson('/api/v1/user/orders', array_merge([
            'address_id' => $this->addressFor($user)->id,
            'payment_method' => 'wallet',
        ], $payload));
    }

    public function test_dashboard_creates_wallet_with_ledger_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/wallet')
            ->assertOk()
            ->assertJsonPath('data.currency', 'INR')
            ->assertJsonPath('data.available_balance', '0.00')
            ->assertJsonPath('data.pending_balance', '0.00')
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertSame('INR', $user->wallet->currency);
        $this->assertEqualsWithDelta(0, $user->wallet->available_balance, 0.01);
    }

    public function test_credit_and_debit_write_a_balanced_ledger(): void
    {
        $user = User::factory()->create();

        $this->service()->credit($user, 100, 'Cashback', WalletTransactionType::Credit);
        $this->service()->debit($user, 40, 'Spend', WalletTransactionType::OrderPayment);

        $transactions = WalletTransaction::orderBy('id')->get();

        $this->assertCount(2, $transactions);
        $this->assertSame(WalletTransactionStatus::Completed, $transactions[0]->status);
        $this->assertSame(0.0, (float) $transactions[0]->balance_before);
        $this->assertSame(100.0, (float) $transactions[0]->balance_after);
        $this->assertSame(100.0, (float) $transactions[1]->balance_before);
        $this->assertSame(60.0, (float) $transactions[1]->balance_after);

        $this->assertEqualsWithDelta(60, $this->service()->balance($user), 0.01);
    }

    public function test_debit_cannot_drive_balance_negative_and_writes_no_ledger(): void
    {
        $user = User::factory()->create();
        $this->service()->credit($user, 50, 'Top up', WalletTransactionType::Recharge);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient wallet balance.');

        try {
            $this->service()->debit($user, 100, 'Too much', WalletTransactionType::OrderPayment);
        } finally {
            $wallet = $user->wallet()->firstOrFail()->fresh();
            $this->assertEqualsWithDelta(50, $wallet->available_balance, 0.01);
            $this->assertGreaterThanOrEqual(0, (float) $wallet->available_balance);
            $this->assertSame(1, WalletTransaction::count());
        }
    }

    public function test_hold_moves_funds_into_pending_escrow(): void
    {
        $user = User::factory()->create();
        $this->service()->credit($user, 500, 'Top up', WalletTransactionType::Recharge);

        $order = $this->orderFor($user);
        $hold = $this->service()->hold($user, 200, $order, 'Payment for order '.$order->order_number);

        $wallet = $user->wallet->refresh();
        $this->assertEqualsWithDelta(300, $wallet->available_balance, 0.01);
        $this->assertEqualsWithDelta(200, $wallet->pending_balance, 0.01);
        $this->assertEqualsWithDelta(500, $wallet->totalBalance(), 0.01);

        $this->assertSame(WalletHoldStatus::Pending, $hold->status);

        $tx = WalletTransaction::where('status', WalletTransactionStatus::Pending->value)->first();
        $this->assertNotNull($tx);
        $this->assertSame(WalletTransactionStatus::Pending, $tx->status);
        $this->assertSame(200.0, (float) $tx->amount);
        $this->assertSame(500.0, (float) $tx->balance_before);
        $this->assertSame(500.0, (float) $tx->balance_after);
    }

    public function test_capture_finalizes_hold_and_updates_ledger(): void
    {
        $user = User::factory()->create();
        $this->service()->credit($user, 500, 'Top up', WalletTransactionType::Recharge);

        $order = $this->orderFor($user);
        $hold = $this->service()->hold($user, 200, $order, 'Payment for order '.$order->order_number);

        $this->service()->captureHold($hold);

        $wallet = $user->wallet->refresh();
        $this->assertEqualsWithDelta(300, $wallet->available_balance, 0.01);
        $this->assertEqualsWithDelta(0, $wallet->pending_balance, 0.01);
        $this->assertEqualsWithDelta(300, $wallet->totalBalance(), 0.01);

        $tx = WalletTransaction::where('status', WalletTransactionStatus::Completed->value)->latest('id')->first();
        $this->assertSame(200.0, (float) $tx->amount);
        $this->assertSame(500.0, (float) $tx->balance_before);
        $this->assertSame(300.0, (float) $tx->balance_after);
    }

    public function test_release_restores_available_balance_and_flags_ledger_failed(): void
    {
        $user = User::factory()->create();
        $this->service()->credit($user, 500, 'Top up', WalletTransactionType::Recharge);

        $order = $this->orderFor($user);
        $hold = $this->service()->hold($user, 200, $order, 'Payment for order '.$order->order_number);

        $this->service()->releaseHold($hold, 'Cancelled');

        $wallet = $user->wallet->refresh();
        $this->assertEqualsWithDelta(500, $wallet->available_balance, 0.01);
        $this->assertEqualsWithDelta(0, $wallet->pending_balance, 0.01);

        $tx = WalletTransaction::where('status', WalletTransactionStatus::Failed->value)->first();
        $this->assertNotNull($tx);
        $this->assertStringContainsString('Cancelled', $tx->description);
    }

    public function test_hold_rejects_amount_larger_than_available(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user);

        $this->expectException(RuntimeException::class);

        try {
            $this->service()->hold($user, 100, $order, 'Overdraw');
        } finally {
            $this->assertSame(0, $user->wallet()->firstOrFail()->fresh()->holds()->count());
        }
    }

    public function test_wallet_order_captures_hold_on_placement(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        app(WalletService::class)->credit($user, 1000, 'Top up', WalletTransactionType::Recharge);

        $product = Product::factory()->create(['price' => 700, 'mrp' => 700, 'gst' => 0, 'stock' => 10]);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $response = $this->placeOrder($user);

        $response->assertCreated()
            ->assertJsonPath('data.order.wallet_amount', 750)
            ->assertJsonPath('data.order.total', 0);

        $order = Order::sole();
        $this->assertSame(1, $order->walletHolds()->count());
        $this->assertSame(WalletHoldStatus::Captured, $order->walletHolds()->first()->status);

        $wallet = $user->wallet->refresh();
        $this->assertEqualsWithDelta(250, $wallet->available_balance, 0.01);
        $this->assertEqualsWithDelta(0, $wallet->pending_balance, 0.01);
    }

    public function test_wallet_order_credits_vendor_wallet_on_placement(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        app(WalletService::class)->credit($user, 1000, 'Top up', WalletTransactionType::Recharge);

        $product = Product::factory()->create(['price' => 700, 'mrp' => 700, 'gst' => 0, 'stock' => 10]);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->placeOrder($user)->assertCreated();

        $vendorOrder = Order::sole()->vendorOrders()->first();
        $commission = $vendorOrder->vendorCommission()->first();

        $this->assertNotNull($commission, 'Vendor should be settled as soon as the wallet payment is captured.');
        $this->assertSame(VendorCommissionStatus::Settled, $commission->status);

        $vendorWallet = $product->vendor->wallet()->first();
        $this->assertNotNull($vendorWallet);
        $this->assertEqualsWithDelta((float) $commission->net_payable, $vendorWallet->available_balance, 0.01);
    }

    public function test_wallet_order_hold_is_released_when_order_is_cancelled(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        app(WalletService::class)->credit($user, 1000, 'Top up', WalletTransactionType::Recharge);

        $product = Product::factory()->create(['price' => 700, 'mrp' => 700, 'gst' => 0, 'stock' => 10]);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->placeOrder($user)->assertCreated();

        $order = Order::sole();
        $hold = $order->walletHolds()->first();
        $this->assertSame(WalletHoldStatus::Captured, $hold->status);

        $wallet = $user->wallet->refresh();
        $this->assertEqualsWithDelta(250, $wallet->available_balance, 0.01);

        $wallet->forceFill([
            'available_balance' => 250,
            'pending_balance' => 750,
        ])->save();
        $hold->forceFill(['status' => WalletHoldStatus::Pending->value, 'captured_at' => null])->save();

        $this->postJson("/api/v1/user/orders/{$order->id}/cancel", ['reason' => 'changed mind'])->assertOk();

        $hold->refresh();
        $this->assertSame(WalletHoldStatus::Released, $hold->status);

        $wallet = $user->wallet->refresh();
        $this->assertEqualsWithDelta(1000, $wallet->available_balance, 0.01);
        $this->assertEqualsWithDelta(0, $wallet->pending_balance, 0.01);
    }

    public function test_failed_razorpay_payment_releases_pending_wallet_hold(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        app(WalletService::class)->credit($user, 1000, 'Top up', WalletTransactionType::Recharge);

        $product = Product::factory()->create(['price' => 700, 'mrp' => 700, 'gst' => 0, 'stock' => 10]);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->placeOrder($user, ['payment_method' => 'razorpay', 'wallet_amount' => 750])->assertCreated();

        $order = Order::sole();
        $hold = $order->walletHolds()->first();
        $this->assertSame(WalletHoldStatus::Pending, $hold->status);

        app(PaymentService::class)->markFailed($order, 'gateway timeout');

        $this->assertSame(WalletHoldStatus::Released, $hold->fresh()->status);
        $this->assertEqualsWithDelta(1000, $user->wallet->refresh()->available_balance, 0.01);
    }

    public function test_transactions_support_direction_and_type_filters(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $service = $this->service();
        $service->recharge($user, 100, 'Added money');
        $service->refund($user, 25, 'Item refunded', $this->orderFor($user));
        $service->debit($user, 10, 'Spend', WalletTransactionType::OrderPayment, $this->orderFor($user));

        $this->getJson('/api/v1/user/wallet/transactions?direction=debit')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');

        $this->getJson('/api/v1/user/wallet/transactions?direction=credit')
            ->assertOk()
            ->assertJsonCount(2, 'data.data');

        $this->getJson('/api/v1/user/wallet/transactions?type=RECHARGE')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_transaction_detail_is_authorized_per_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $service = $this->service();
        $service->recharge($owner, 100, 'Added money');

        $tx = $owner->wallet->transactions()->first();

        $this->getJson("/api/v1/user/wallet/transactions/{$tx->id}")->assertNotFound();

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/user/wallet/transactions/{$tx->id}")
            ->assertOk()
            ->assertJsonPath('data.type', WalletTransactionType::Recharge->value)
            ->assertJsonPath('data.amount', 100);
    }

    public function test_recharge_initiates_payment_and_confirmation_credits_wallet(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $init = $this->postJson('/api/v1/user/wallet/recharge', ['amount' => 250])
            ->assertCreated()
            ->assertJsonPath('data.razorpay.order_id', 'order_rzp_test');

        $payment = Payment::whereNull('order_id')->where('user_id', $user->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('PENDING', $payment->status);
        $this->assertSame(250.0, (float) $payment->amount);

        $secret = config('services.razorpay.key_secret');
        $signature = hash_hmac('sha256', 'order_rzp_test|pay_rzp_123', $secret);

        $this->postJson('/api/v1/user/wallet/recharge/verify', [
            'razorpay_order_id' => 'order_rzp_test',
            'razorpay_payment_id' => 'pay_rzp_123',
            'razorpay_signature' => $signature,
        ])->assertOk()
            ->assertJsonPath('data.available_balance', '250.00');

        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertEqualsWithDelta(250, app(WalletService::class)->balance($user), 0.01);
    }

    public function test_recharge_initiation_requires_valid_amount(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/wallet/recharge', ['amount' => 0])->assertStatus(422);
        $this->assertSame(0, Payment::count());
    }

    protected function orderFor(User $user): Order
    {
        return Order::factory()->create(['user_id' => $user->id, 'total' => 0]);
    }
}
