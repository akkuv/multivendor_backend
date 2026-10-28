<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\VendorCommissionStatus;
use App\Enums\VendorOrderStatus;
use App\Enums\WalletTransactionType;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\User;
use App\Models\VendorOrder;
use App\Models\VendorProfile;
use App\Services\Refund\RefundService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CancellationPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Place a COD order for a fresh customer and return the customer, the
     * product and the resulting master order.
     *
     * @return array{0: User, 1: Product, 2: Order}
     */
    protected function placedOrder(): array
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 200, 'mrp' => 200, 'gst' => 0, 'stock' => 5]);
        $user = User::factory()->customer()->create();

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();
        $this->postJson('/api/v1/user/orders', [
            'address_id' => Address::factory()->create(['user_id' => $user->id])->id,
            'payment_method' => 'cod',
        ])->assertCreated();

        return [$user, $product, Order::latest('id')->first()];
    }

    /**
     * Place and pay for a wallet order for a fresh customer.
     *
     * @return array{0: User, 1: Product, 2: Order, 3: VendorOrder}
     */
    protected function placedWalletOrder(): array
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 200, 'mrp' => 200, 'gst' => 0, 'stock' => 5]);
        $user = User::factory()->customer()->create();

        app(WalletService::class)->credit($user, 1000, 'Top up', WalletTransactionType::Recharge);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();
        $this->postJson('/api/v1/user/orders', [
            'address_id' => Address::factory()->create(['user_id' => $user->id])->id,
            'payment_method' => 'wallet',
        ])->assertCreated();

        $order = Order::latest('id')->first();

        return [$user, $product, $order, $order->vendorOrders()->first()];
    }

    public function test_customer_cannot_cancel_a_delivered_order(): void
    {
        [$user, , $order] = $this->placedOrder();

        $order->forceFill([
            'order_status' => OrderStatus::Delivered->value,
            'payment_status' => 'PAID',
            'paid_at' => now(),
        ])->save();

        $this->postJson("/api/v1/user/orders/{$order->id}/cancel")
            ->assertStatus(422);

        $this->assertSame(OrderStatus::Delivered->value, $order->fresh()->order_status);
    }

    public function test_customer_cancel_releases_reserved_stock_and_cancels_sub_orders(): void
    {
        [$user, $product, $order] = $this->placedOrder();

        $this->assertSame(2, (int) $product->refresh()->reserved_stock);

        $this->postJson("/api/v1/user/orders/{$order->id}/cancel", ['reason' => 'Changed mind'])->assertOk();

        $this->assertSame(OrderStatus::Cancelled->value, $order->fresh()->order_status);
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->assertSame(0, (int) $product->refresh()->reserved_stock);
        $this->assertSame(VendorOrderStatus::Cancelled->value, $order->vendorOrders()->first()->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled->value, $order->items()->first()->fresh()->status);
    }

    public function test_customer_cancel_releases_reserved_variant_stock(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 250, 'mrp' => 250, 'gst' => 0, 'stock' => 0]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 250, 'stock' => 6]);
        $user = User::factory()->customer()->create();

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2])->assertOk();
        $this->postJson('/api/v1/user/orders', [
            'address_id' => Address::factory()->create(['user_id' => $user->id])->id,
            'payment_method' => 'cod',
        ])->assertCreated();

        $order = Order::latest('id')->first();

        $this->assertSame(2, (int) $variant->refresh()->reserved_stock);
        $this->assertSame($variant->id, (int) $order->items()->first()->product_variant_id);

        $this->postJson("/api/v1/user/orders/{$order->id}/cancel", ['reason' => 'Changed mind'])->assertOk();

        $this->assertSame(0, (int) $variant->refresh()->reserved_stock);
    }

    public function test_admin_cannot_cancel_a_delivered_order(): void
    {
        [, , $order] = $this->placedOrder();

        $order->forceFill([
            'order_status' => OrderStatus::Delivered->value,
            'payment_status' => 'PAID',
            'paid_at' => now(),
        ])->save();

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->putJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'CANCELLED'])
            ->assertStatus(422);
    }

    public function test_vendor_can_cancel_only_within_allowed_stages(): void
    {
        [, , $order] = $this->placedOrder();
        $vendorOrder = $order->vendorOrders()->first();

        Sanctum::actingAs($vendorOrder->vendor->user);
        $this->putJson("/api/v1/vendor/orders/{$vendorOrder->id}/status", ['status' => 'CANCELLED'])
            ->assertOk()
            ->assertJsonPath('data.vendor_order.status', VendorOrderStatus::Cancelled->value);

        [, , $second] = $this->placedOrder();
        $secondVendorOrder = $second->vendorOrders()->first();
        $secondVendorOrder->forceFill(['status' => VendorOrderStatus::Shipped->value])->save();

        Sanctum::actingAs($secondVendorOrder->vendor->user);

        $this->putJson("/api/v1/vendor/orders/{$secondVendorOrder->id}/status", ['status' => 'CANCELLED'])
            ->assertStatus(422);

        $this->assertSame(VendorOrderStatus::Shipped->value, $secondVendorOrder->fresh()->status);
    }

    public function test_vendor_cancellation_refunds_wallet_order_and_reverses_settlement(): void
    {
        [$user, , , $vendorOrder] = $this->placedWalletOrder();

        $paid = (float) $vendorOrder->wallet_amount;
        $this->assertGreaterThan(0, $paid);

        $commission = $vendorOrder->vendorCommission()->first();
        $this->assertNotNull($commission);
        $this->assertSame(VendorCommissionStatus::Settled, $commission->status);

        $this->assertEqualsWithDelta(
            1000 - $paid,
            (float) $user->wallet()->firstOrFail()->fresh()->available_balance,
            0.01,
        );

        Sanctum::actingAs($vendorOrder->vendor->user);
        $this->putJson("/api/v1/vendor/orders/{$vendorOrder->id}/status", ['status' => 'CANCELLED'])
            ->assertOk()
            ->assertJsonPath('data.vendor_order.status', VendorOrderStatus::Cancelled->value);

        $refund = Refund::where('vendor_order_id', $vendorOrder->id)->first();

        $this->assertNotNull($refund);
        $this->assertSame(RefundStatus::Completed->value, $refund->status);
        $this->assertSame('WALLET', $refund->method);
        $this->assertEqualsWithDelta($paid, (float) $refund->wallet_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $refund->gateway_amount, 0.01);

        $this->assertEqualsWithDelta(
            1000,
            (float) $user->wallet()->firstOrFail()->fresh()->available_balance,
            0.01,
        );

        $this->assertSame(VendorCommissionStatus::Reversed, $commission->fresh()->status);
        $this->assertEqualsWithDelta(
            0,
            (float) $vendorOrder->vendor->wallet()->firstOrFail()->fresh()->available_balance,
            0.01,
        );
    }

    public function test_cancellation_refund_is_idempotent(): void
    {
        [$user, , , $vendorOrder] = $this->placedWalletOrder();

        $service = app(RefundService::class);

        $first = $service->refundVendorOrderCancellation($vendorOrder, 'Changed mind');
        $second = $service->refundVendorOrderCancellation($vendorOrder, 'Changed mind');

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Refund::where('vendor_order_id', $vendorOrder->id)->count());
        $this->assertSame(
            1,
            $user->wallet()->firstOrFail()->transactions()
                ->where('type', WalletTransactionType::Refund->value)
                ->count(),
        );
    }

    public function test_admin_master_cancellation_refunds_gateway_payment(): void
    {
        Http::fake([
            'api.razorpay.com/*/refund' => Http::response(['id' => 'rfnd_cancel_test', 'status' => 'processed'], 200),
            'api.razorpay.com/*' => Http::response(['id' => 'order_rzp_test', 'amount' => 1, 'currency' => 'INR', 'receipt' => 'TEST'], 200),
        ]);

        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 200, 'mrp' => 200, 'gst' => 0, 'stock' => 5]);
        $user = User::factory()->customer()->create();

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();
        $this->postJson('/api/v1/user/orders', [
            'address_id' => Address::factory()->create(['user_id' => $user->id])->id,
            'payment_method' => 'razorpay',
        ])->assertCreated();

        $order = Order::latest('id')->first();
        $vendorOrder = $order->vendorOrders()->first();

        $order->forceFill([
            'order_status' => OrderStatus::Confirmed->value,
            'payment_status' => 'PAID',
            'razorpay_payment_id' => 'pay_cancel_test',
            'paid_at' => now(),
        ])->save();

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->putJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'CANCELLED'])
            ->assertOk()
            ->assertJsonPath('data.order.order_status', OrderStatus::Cancelled->value);

        $refund = Refund::where('vendor_order_id', $vendorOrder->id)->first();

        $this->assertNotNull($refund);
        $this->assertSame(RefundStatus::Completed->value, $refund->status);
        $this->assertSame('GATEWAY', $refund->method);
        $this->assertEqualsWithDelta((float) $order->total, (float) $refund->gateway_amount, 0.01);
        $this->assertSame('rfnd_cancel_test', $refund->razorpay_refund_id);
        $this->assertSame(VendorOrderStatus::Cancelled->value, $vendorOrder->fresh()->status);
    }

    public function test_cancelled_cod_order_creates_no_refund(): void
    {
        [$user, , $order] = $this->placedOrder();

        $this->postJson("/api/v1/user/orders/{$order->id}/cancel", ['reason' => 'Changed mind'])->assertOk();

        $this->assertSame(0, Refund::count());
    }
}
