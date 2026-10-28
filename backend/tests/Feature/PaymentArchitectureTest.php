<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\WalletHoldStatus;
use App\Enums\WalletTransactionType;
use App\Events\OrderPlaced;
use App\Events\PaymentSucceeded;
use App\Events\RefundCompleted;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\PaymentWebhook;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected string $keyId = 'rzp_test_key';

    protected string $keySecret = 'rzp_test_secret';

    protected string $webhookSecret = 'webhook_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([OrderPlaced::class, PaymentSucceeded::class, RefundCompleted::class]);

        config()->set('services.razorpay.key_id', $this->keyId);
        config()->set('services.razorpay.key_secret', $this->keySecret);
        config()->set('services.razorpay.webhook_secret', $this->webhookSecret);

        $orderId = 'order_rzp_test';
        $paymentId = 'pay_rzp_test';

        Http::fake([
            'api.razorpay.com/*' => function (Request $request) use ($orderId, $paymentId) {
                $url = $request->url();

                if ($request->method() === 'POST' && str_ends_with($url, '/orders')) {
                    return Http::response([
                        'id' => $orderId,
                        'amount' => 1,
                        'currency' => 'INR',
                        'receipt' => 'ORD-TEST',
                    ], 200);
                }

                if ($request->method() === 'POST' && str_contains($url, '/refund')) {
                    return Http::response([
                        'id' => 'rfnd_webhook_refund',
                        'payment_id' => $paymentId,
                        'status' => 'processed',
                    ], 200);
                }

                if (str_contains($url, '/capture') || $request->method() === 'GET') {
                    return Http::response([
                        'id' => $paymentId,
                        'order_id' => $orderId,
                        'status' => 'captured',
                        'amount' => 1,
                        'currency' => 'INR',
                    ], 200);
                }

                return Http::response([], 404);
            },
        ]);
    }

    protected function addressFor(User $user): Address
    {
        return Address::factory()->create(['user_id' => $user->id]);
    }

    protected function placeOrder(User $user, Address $address, array $payload = []): TestResponse
    {
        return $this->postJson('/api/v1/user/orders', array_merge([
            'address_id' => $address->id,
            'payment_method' => 'cod',
        ], $payload));
    }

    protected function codOrder(User $user, float $price = 500, ?int $vendorId = null): Order
    {
        $product = Product::factory()->create([
            'vendor_id' => $vendorId ?? VendorProfile::factory()->approved()->create()->id,
            'price' => $price,
            'stock' => 10,
        ]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->placeOrder($user, $this->addressFor($user))->assertCreated();

        return Order::first();
    }

    protected function signatureFor(array $event): string
    {
        $body = json_encode($event);

        return base64_encode(hash_hmac('sha256', $body, $this->webhookSecret, true));
    }

    // ------------------------------------------------------------------ //
    // COD Flow
    // ------------------------------------------------------------------ //

    public function test_cod_order_creates_cod_pending_payment_without_gateway(): void
    {
        $user = User::factory()->customer()->create();
        Sanctum::actingAs($user);

        $order = $this->codOrder($user);

        $this->assertSame(PaymentStatus::CodPending->value, $order->payment_status);
        $this->assertSame(PaymentMethod::Cod->value, $order->payment_method);

        $payment = $order->payments()->first();

        $this->assertNotNull($payment);
        $this->assertSame('cod', $payment->gateway);
        $this->assertSame(PaymentMethod::Cod->value, $payment->payment_method);
        $this->assertSame(PaymentStatus::Pending->value, $payment->status);
        $this->assertNull($payment->gateway_order_id);
        $this->assertSame('INR', $payment->currency);
    }

    public function test_cod_order_is_marked_paid_when_vendor_delivers(): void
    {
        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        $vendor = User::factory()->vendor()->create();
        $profile = $vendor->vendorProfile()->create([
            'store_name' => 'COD Vendor',
            'slug' => 'cod-vendor',
            'status' => 'APPROVED',
            'is_approved' => true,
            'commission_rate' => 10,
        ]);

        $order = $this->codOrder($customer, 500, $profile->id);

        $item = $order->items()->first();
        $this->assertNotNull($item);

        Sanctum::actingAs($vendor);
        $this->putJson("/api/v1/vendor/order-items/{$item->id}/status", ['status' => OrderStatus::Delivered->value])->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid->value, $order->payment_status);
        $this->assertNotNull($order->paid_at);

        $payment = $order->payments()->first();
        $this->assertSame(PaymentStatus::Paid->value, $payment->status);
        $this->assertSame('cod_collected', $payment->metadata['flow']);
    }

    public function test_cod_rejected_when_method_disabled(): void
    {
        $user = User::factory()->customer()->create();
        Sanctum::actingAs($user);

        PaymentSetting::query()->firstOrCreate(['id' => 1])->update(['cod_enabled' => false]);

        $product = Product::factory()->create([
            'vendor_id' => VendorProfile::factory()->approved()->create()->id,
            'price' => 500,
            'stock' => 10,
        ]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->placeOrder($user, $this->addressFor($user))
            ->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_cod_respects_minimum_amount(): void
    {
        $user = User::factory()->customer()->create();
        Sanctum::actingAs($user);

        PaymentSetting::query()->firstOrCreate(['id' => 1])->update(['cod_min_amount' => 5000]);

        $product = Product::factory()->create([
            'vendor_id' => VendorProfile::factory()->approved()->create()->id,
            'price' => 500,
            'stock' => 10,
        ]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->placeOrder($user, $this->addressFor($user))
            ->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_cod_respects_maximum_amount(): void
    {
        $user = User::factory()->customer()->create();
        Sanctum::actingAs($user);

        PaymentSetting::query()->firstOrCreate(['id' => 1])->update(['cod_max_amount' => 100]);

        $product = Product::factory()->create([
            'vendor_id' => VendorProfile::factory()->approved()->create()->id,
            'price' => 500,
            'stock' => 10,
        ]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->placeOrder($user, $this->addressFor($user))
            ->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    // ------------------------------------------------------------------ //
    // Payment Method Gating
    // ------------------------------------------------------------------ //

    public function test_razorpay_rejected_when_method_disabled(): void
    {
        $user = User::factory()->customer()->create();
        Sanctum::actingAs($user);

        PaymentSetting::query()->firstOrCreate(['id' => 1])->update(['razorpay_enabled' => false]);

        $product = Product::factory()->create([
            'vendor_id' => VendorProfile::factory()->approved()->create()->id,
            'price' => 500,
            'stock' => 10,
        ]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->placeOrder($user, $this->addressFor($user), ['payment_method' => 'razorpay'])
            ->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_payment_options_expose_enabled_methods_and_cod_bounds(): void
    {
        PaymentSetting::query()->firstOrCreate(['id' => 1])->update([
            'cod_enabled' => true,
            'cod_min_amount' => 100,
            'cod_max_amount' => 10000,
            'cod_fee' => 30,
        ]);

        $this->getJson('/api/v1/payment-options')
            ->assertOk()
            ->assertJsonPath('data.razorpay.enabled', true)
            ->assertJsonPath('data.wallet.enabled', true)
            ->assertJsonPath('data.cod.enabled', true)
            ->assertJsonPath('data.cod.min_amount', 100)
            ->assertJsonPath('data.cod.max_amount', 10000)
            ->assertJsonPath('data.cod.fee', 30)
            ->assertJsonCount(3, 'data.methods');
    }

    public function test_admin_can_update_payment_settings(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/payment-settings', [
            'razorpay_enabled' => false,
            'cod_enabled' => false,
            'cod_min_amount' => 250,
            'cod_fee' => 25,
            'free_delivery_threshold' => 999.99,
        ])->assertOk()
            ->assertJsonPath('data.razorpay_enabled', false)
            ->assertJsonPath('data.cod_enabled', false)
            ->assertJsonPath('data.cod_min_amount', '250.00')
            ->assertJsonPath('data.cod_fee', '25.00')
            ->assertJsonPath('data.free_delivery_threshold', '999.99');

        $this->getJson('/api/v1/payment-options')
            ->assertOk()
            ->assertJsonPath('data.cod.enabled', false)
            ->assertJsonPath('data.free_delivery.threshold', 999.99);
    }

    // ------------------------------------------------------------------ //
    // Razorpay + Hybrid (Wallet + Gateway)
    // ------------------------------------------------------------------ //

    public function test_hybrid_payment_charges_gateway_only_net_of_wallet_and_captures_hold(): void
    {
        $user = User::factory()->customer()->create();
        Sanctum::actingAs($user);

        app(WalletService::class)->credit($user, 2000, 'Test balance', WalletTransactionType::Credit);
        $wallet = app(WalletService::class)->walletFor($user);
        $this->assertSame('2000.00', $wallet->available_balance);

        $product = Product::factory()->create([
            'vendor_id' => VendorProfile::factory()->approved()->create()->id,
            'price' => 3000,
            'stock' => 10,
        ]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $response = $this->placeOrder($user, $this->addressFor($user), [
            'payment_method' => 'razorpay',
            'wallet_amount' => 2000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.order.wallet_amount', 2000);

        $orderTotal = (float) $response->json('data.order.total');

        Http::assertSent(function ($request) use ($orderTotal): bool {
            return $request->url() === 'https://api.razorpay.com/v1/orders'
                && data_get($request->data(), 'amount') === (int) round($orderTotal * 100);
        });

        $order = Order::first();
        $this->assertSame($orderTotal, (float) $order->total);
        $payment = $order->payments()->first();

        $this->assertSame($orderTotal, (float) $payment->amount);
        $this->assertSame('order_rzp_test', $payment->gateway_order_id);

        $hold = $order->walletHolds()->first();
        $this->assertSame(WalletHoldStatus::Pending, $hold?->status);

        $signature = hash_hmac('sha256', 'order_rzp_test|pay_rzp_test', $this->keySecret);

        $this->postJson('/api/v1/user/payments/verify', [
            'order_id' => $order->id,
            'razorpay_order_id' => 'order_rzp_test',
            'razorpay_payment_id' => 'pay_rzp_test',
            'razorpay_signature' => $signature,
        ])->assertOk()
            ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value);

        $order->refresh();
        $payment->refresh();

        $this->assertSame(PaymentStatus::Paid->value, $order->payment_status);
        $this->assertSame(PaymentStatus::Paid->value, $payment->status);
        $this->assertSame('pay_rzp_test', $payment->gateway_payment_id);

        $this->assertSame(1, $payment->transactions()->where('type', 'CAPTURED')->count());

        $hold->refresh();
        $this->assertSame(WalletHoldStatus::Captured, $hold->status);
        $this->assertSame('0.00', $wallet->fresh()->available_balance);
    }

    public function test_payment_verification_rejects_bad_signature(): void
    {
        $user = User::factory()->customer()->create();
        Sanctum::actingAs($user);

        $order = $this->placeOrderForRazorpay($user);

        $this->postJson('/api/v1/user/payments/verify', [
            'order_id' => $order->id,
            'razorpay_order_id' => 'order_rzp_test',
            'razorpay_payment_id' => 'pay_rzp_test',
            'razorpay_signature' => 'invalid-signature',
        ])->assertStatus(400);

        $order->refresh();
        $this->assertSame(PaymentStatus::Failed->value, $order->payment_status);
    }

    protected function placeOrderForRazorpay(User $user, float $price = 500): Order
    {
        $product = Product::factory()->create([
            'vendor_id' => VendorProfile::factory()->approved()->create()->id,
            'price' => $price,
            'stock' => 10,
        ]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $response = $this->placeOrder($user, $this->addressFor($user), ['payment_method' => 'razorpay']);
        $response->assertCreated();

        return Order::first();
    }

    // ------------------------------------------------------------------ //
    // Webhooks
    // ------------------------------------------------------------------ //

    public function test_webhook_rejects_bad_signature_and_persists_nothing_until_valid(): void
    {
        $event = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => ['id' => 'pay_rzp_test', 'order_id' => 'order_rzp_test'],
                ],
            ],
        ];

        $this->postJson('/api/v1/payments/webhook', $event, [
            'X-Razorpay-Signature' => 'bad-signature',
        ])->assertStatus(400);

        $this->assertSame(0, PaymentWebhook::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_webhook_payment_captured_finalizes_payment_and_is_persisted(): void
    {
        $user = User::factory()->customer()->create();
        Sanctum::actingAs($user);

        $order = $this->placeOrderForRazorpay($user);

        $event = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_rzp_test',
                        'order_id' => 'order_rzp_test',
                        'status' => 'captured',
                        'amount' => 1000,
                        'currency' => 'INR',
                    ],
                ],
            ],
        ];

        $this->postJson('/api/v1/payments/webhook', $event, [
            'X-Razorpay-Signature' => $this->signatureFor($event),
        ])->assertOk()
            ->assertJsonPath('data.event', 'payment.captured')
            ->assertJsonPath('data.status', 'PROCESSED');

        $webhook = PaymentWebhook::first();
        $this->assertNotNull($webhook);
        $this->assertTrue($webhook->verified);
        $this->assertSame('payment.captured', $webhook->event);
        $this->assertSame('pay_rzp_test', $webhook->gateway_payment_id);
        $this->assertSame('order_rzp_test', $webhook->gateway_order_id);

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid->value, $order->payment_status);
        $this->assertSame(OrderStatus::Confirmed->value, $order->order_status);

        $payment = $order->payments()->first();
        $this->assertSame(PaymentStatus::Paid->value, $payment->status);
        $this->assertSame('pay_rzp_test', $payment->gateway_payment_id);
        $this->assertSame(1, $payment->transactions()->where('type', 'CAPTURED')->count());
    }

    public function test_webhook_refund_processed_finalizes_refund(): void
    {
        $user = User::factory()->customer()->create();
        Sanctum::actingAs($user);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'payment_method' => PaymentMethod::Razorpay->value,
            'payment_status' => PaymentStatus::Paid->value,
        ]);

        $refund = Refund::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'razorpay_refund_id' => 'rfnd_webhook_refund',
            'status' => RefundStatus::Pending->value,
        ]);

        $event = [
            'event' => 'refund.processed',
            'payload' => [
                'refund' => [
                    'entity' => [
                        'id' => 'rfnd_webhook_refund',
                        'payment_id' => 'pay_rzp_test',
                        'status' => 'processed',
                    ],
                ],
            ],
        ];

        $this->postJson('/api/v1/payments/webhook', $event, [
            'X-Razorpay-Signature' => $this->signatureFor($event),
        ])->assertOk()
            ->assertJsonPath('data.status', 'PROCESSED');

        $refund->refresh();
        $this->assertSame(RefundStatus::Completed->value, $refund->status);
        $this->assertNotNull($refund->processed_at);
    }
}
