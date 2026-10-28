<?php

namespace Tests\Feature;

use App\Enums\AdminLedgerType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Address;
use App\Models\AdminFinancialLedger;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\Refund;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\Refund\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReturnFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'api.razorpay.com/*/refund' => Http::response([
                'id' => 'rfnd_gateway_test',
                'status' => 'processed',
            ], 200),
            'api.razorpay.com/*' => Http::response([], 200),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function deliveredOrder(array $orderOverrides = [], array $productOverrides = []): array
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(array_merge(
            ['vendor_id' => $vendor->id, 'price' => 200, 'mrp' => 200, 'gst' => 0, 'stock' => 10],
            $productOverrides,
        ));

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();
        $this->postJson('/api/v1/user/orders', [
            'address_id' => Address::factory()->create(['user_id' => $customer->id])->id,
            'payment_method' => 'cod',
        ])->assertCreated();

        $order = Order::latest('id')->first();
        $vendorOrder = $order->vendorOrders()->first();

        $vendorOrder->forceFill(['status' => VendorOrderStatus::Delivered->value, 'delivered_at' => now()])->save();
        $order->forceFill(array_merge([
            'order_status' => OrderStatus::Delivered->value,
            'payment_status' => PaymentStatus::Paid->value,
            'paid_at' => now(),
        ], $orderOverrides))->save();

        return [
            'vendor' => $vendor,
            'product' => $product,
            'customer' => $customer,
            'order' => $order->fresh(),
            'vendorOrder' => $vendorOrder,
            'item' => $order->items()->first(),
        ];
    }

    public function test_customer_requests_partial_return_with_quantity_and_images(): void
    {
        $c = $this->deliveredOrder();

        Sanctum::actingAs($c['customer']);

        $response = $this->postJson('/api/v1/user/returns', [
            'order_item_id' => $c['item']->id,
            'quantity' => 1,
            'reason' => 'Damaged',
            'description' => 'Scratch on sleeve.',
            'images' => ['https://cdn.test/a.jpg', 'https://cdn.test/b.jpg'],
        ])->assertCreated()->json('data');

        $this->assertSame(ReturnStatus::Requested->value, $response['status']);
        $this->assertSame(1, $response['quantity']);
        $this->assertSame(['https://cdn.test/a.jpg', 'https://cdn.test/b.jpg'], $response['images']);
        $this->assertSame(OrderStatus::ReturnRequested->value, $c['order']->fresh()->order_status);
    }

    public function test_return_rejected_for_non_delivered_order_and_duplicate(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 200, 'mrp' => 200, 'gst' => 0, 'stock' => 5]);
        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $this->postJson('/api/v1/user/orders', [
            'address_id' => Address::factory()->create(['user_id' => $customer->id])->id,
            'payment_method' => 'cod',
        ])->assertCreated();

        $order = Order::first();
        $item = $order->items()->first();

        $this->postJson('/api/v1/user/returns', ['order_item_id' => $item->id, 'reason' => 'Changed mind'])
            ->assertStatus(422);

        $c = $this->deliveredOrder();

        Sanctum::actingAs($c['customer']);
        $this->postJson('/api/v1/user/returns', ['order_item_id' => $c['item']->id, 'reason' => 'Damaged'])->assertCreated();
        $this->postJson('/api/v1/user/returns', ['order_item_id' => $c['item']->id, 'reason' => 'Duplicate'])
            ->assertStatus(422);
    }

    public function test_customer_can_withdraw_a_pending_return(): void
    {
        $c = $this->deliveredOrder();
        Sanctum::actingAs($c['customer']);
        $return = $this->postJson('/api/v1/user/returns', ['order_item_id' => $c['item']->id, 'reason' => 'Not as described'])
            ->assertCreated()->json('data');

        $this->postJson("/api/v1/user/returns/{$return['id']}/withdraw")
            ->assertOk()
            ->assertJsonPath('data.status', ReturnStatus::Closed->value);

        $this->assertSame(OrderStatus::Delivered->value, $c['order']->fresh()->order_status);
    }

    public function test_customer_can_request_a_return_again_after_previous_one_is_closed(): void
    {
        $c = $this->deliveredOrder();
        Sanctum::actingAs($c['customer']);

        $first = $this->postJson('/api/v1/user/returns', [
            'order_item_id' => $c['item']->id,
            'reason' => 'Changed mind',
        ])->assertCreated()->json('data');

        // A second request while the first is still active stays rejected.
        $this->postJson('/api/v1/user/returns', [
            'order_item_id' => $c['item']->id,
            'reason' => 'Duplicate',
        ])->assertStatus(422);

        $this->postJson("/api/v1/user/returns/{$first['id']}/withdraw")->assertOk();

        // Once closed, the item is no longer flagged with an active return…
        $detail = $this->getJson("/api/v1/user/orders/{$c['order']->id}")->assertOk()->json('data');
        $this->assertFalse($detail['order']['items'][0]['has_active_return']);

        // …and a fresh return request is accepted (the old unique index used to block it).
        $this->postJson('/api/v1/user/returns', [
            'order_item_id' => $c['item']->id,
            'reason' => 'Damaged on arrival',
        ])->assertCreated();

        $detail = $this->getJson("/api/v1/user/orders/{$c['order']->id}")->assertOk()->json('data');
        $this->assertTrue($detail['order']['items'][0]['has_active_return']);
    }

    public function test_admin_review_and_refund_lifecycle_for_wallet_order(): void
    {
        $c = $this->deliveredOrder();
        $stockBefore = $c['product']->fresh()->stock;
        Sanctum::actingAs($c['customer']);
        $return = $this->postJson('/api/v1/user/returns', [
            'order_item_id' => $c['item']->id,
            'quantity' => 2,
            'reason' => 'Damaged',
        ])->assertCreated()->json('data');

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->putJson("/api/v1/admin/returns/{$return['id']}/status", ['status' => 'APPROVED'])->assertOk();
        $this->putJson("/api/v1/admin/returns/{$return['id']}/status", ['status' => 'PICKUP_SCHEDULED'])->assertOk();
        $this->putJson("/api/v1/admin/returns/{$return['id']}/status", ['status' => 'PICKED_UP'])->assertOk();
        $this->putJson("/api/v1/admin/returns/{$return['id']}/status", ['status' => 'RECEIVED'])->assertOk();
        $this->putJson("/api/v1/admin/returns/{$return['id']}/status", ['status' => 'INSPECTING'])->assertOk();

        $this->putJson("/api/v1/admin/returns/{$return['id']}/status", ['status' => 'APPROVED_FOR_REFUND'])
            ->assertOk();

        $refund = Refund::where('product_return_id', $return['id'])->first();

        $this->assertNotNull($refund);
        $this->assertSame(RefundStatus::Pending->value, $refund->status);
        $this->assertSame('450.00', $refund->amount);
        $this->assertSame(2, $refund->quantity);

        $this->postJson("/api/v1/admin/refunds/{$refund->id}/process")
            ->assertOk()
            ->assertJsonPath('data.refund.method', 'WALLET');

        $refund->refresh();

        $this->assertSame(RefundStatus::Completed->value, $refund->status);
        $this->assertSame('WALLET', $refund->method);
        $this->assertSame('450.00', $refund->wallet_amount);
        $this->assertSame('0.00', $refund->gateway_amount);

        $this->assertSame(
            $stockBefore + 2,
            (int) $c['product']->fresh()->stock,
        );

        $this->assertSame(
            450.0,
            (float) $c['customer']->wallet()->firstOrFail()->fresh()->available_balance,
        );

        $this->assertSame(ReturnStatus::Refunded->value, $c['order']->fresh()->returns()->first()->status);
        $this->assertSame(OrderStatus::Refunded->value, $c['order']->fresh()->order_status);
        $this->assertSame(PaymentStatus::Refunded->value, $c['order']->fresh()->payment_status);

        $ledger = AdminFinancialLedger::where('type', AdminLedgerType::Refund->value)->first();
        $this->assertNotNull($ledger);
        $this->assertSame((int) $c['order']->id, $ledger->order_id);
        $this->assertSame('450.00', $ledger->amount);

        $this->assertSame(
            1,
            InventoryTransaction::where('product_id', $c['product']->id)
                ->where('type', 'RETURN')
                ->count(),
        );
    }

    public function test_invalid_admin_transition_is_rejected(): void
    {
        $c = $this->deliveredOrder();
        Sanctum::actingAs($c['customer']);
        $return = $this->postJson('/api/v1/user/returns', ['order_item_id' => $c['item']->id, 'reason' => 'Damaged'])
            ->assertCreated()->json('data');

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->putJson("/api/v1/admin/returns/{$return['id']}/status", ['status' => 'RECEIVED'])
            ->assertStatus(422);
    }

    public function test_hybrid_wallet_gateway_order_splits_refund_between_sources(): void
    {
        $c = $this->deliveredOrder([
            'wallet_amount' => 150,
            'razorpay_payment_id' => 'pay_rzp_hybrid',
            'payment_method' => 'razorpay',
        ]);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $return = ProductReturn::factory()->create([
            'order_id' => $c['order']->id,
            'order_item_id' => $c['item']->id,
            'user_id' => $c['customer']->id,
            'quantity' => 1,
        ]);

        $refund = app(RefundService::class)->initiateForReturn($return);
        $this->assertSame('225.00', $refund->amount);

        $this->postJson("/api/v1/admin/refunds/{$refund->id}/process")
            ->assertOk()
            ->assertJsonPath('data.refund.method', 'SPLIT');

        $refund->refresh();

        $this->assertSame('75.00', $refund->wallet_amount);
        $this->assertSame('150.00', $refund->gateway_amount);
        $this->assertSame('rfnd_gateway_test', $refund->razorpay_refund_id);

        $this->assertSame(75.0, (float) $c['customer']->wallet()->firstOrFail()->fresh()->available_balance);

        $this->assertSame(
            1,
            $c['customer']->wallet()->firstOrFail()->transactions()->count(),
        );
    }

    public function test_vendor_sees_only_their_own_returns(): void
    {
        $c = $this->deliveredOrder();
        $other = VendorProfile::factory()->approved()->create();
        $otherProduct = Product::factory()->create(['vendor_id' => $other->id, 'price' => 100, 'mrp' => 100, 'gst' => 0, 'stock' => 5]);
        OrderItem::create([
            'order_id' => $c['order']->id,
            'vendor_order_id' => $c['vendorOrder']->id,
            'vendor_id' => $other->id,
            'product_id' => $otherProduct->id,
            'product_name' => 'Other item',
            'unit_price' => 100,
            'quantity' => 1,
            'total' => 100,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'status' => 'DELIVERED',
        ]);
        $otherReturn = ProductReturn::factory()->create([
            'order_id' => $c['order']->id,
            'order_item_id' => $c['order']->items()->where('vendor_id', $other->id)->first()->id,
            'user_id' => $c['customer']->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($c['vendor']->user);
        $this->getJson('/api/v1/vendor/returns')
            ->assertOk()
            ->assertJsonCount(0, 'data.data');

        Sanctum::actingAs($other->user);
        $this->getJson('/api/v1/vendor/returns')->assertOk()->assertJsonCount(1, 'data.data');
        $this->getJson("/api/v1/vendor/returns/{$otherReturn->id}")->assertOk();

        $ownerReturn = ProductReturn::factory()->create([
            'order_id' => $c['order']->id,
            'order_item_id' => $c['item']->id,
            'user_id' => $c['customer']->id,
            'quantity' => 1,
        ]);
        $this->getJson("/api/v1/vendor/returns/{$ownerReturn->id}")
            ->assertStatus(404);
    }

    public function test_vendor_can_confirm_reject_and_cancel_customer_return_request(): void
    {
        $reject = $this->deliveredOrder();
        Sanctum::actingAs($reject['customer']);
        $rejected = $this->postJson('/api/v1/user/returns', [
            'order_item_id' => $reject['item']->id,
            'quantity' => 1,
            'reason' => 'Damaged',
        ])->assertCreated()->json('data');

        Sanctum::actingAs($reject['vendor']->user);
        $this->putJson("/api/v1/vendor/returns/{$rejected['id']}/status", ['status' => 'REJECTED', 'notes' => 'Item used'])
            ->assertOk()
            ->assertJsonPath('data.status', ReturnStatus::Rejected->value);

        $approve = $this->deliveredOrder();
        Sanctum::actingAs($approve['customer']);
        $approved = $this->postJson('/api/v1/user/returns', [
            'order_item_id' => $approve['item']->id,
            'quantity' => 1,
            'reason' => 'Wrong size',
        ])->assertCreated()->json('data');

        Sanctum::actingAs($approve['vendor']->user);
        $this->putJson("/api/v1/vendor/returns/{$approved['id']}/status", ['status' => 'APPROVED', 'notes' => 'Accepting return'])
            ->assertOk()
            ->assertJsonPath('data.status', ReturnStatus::Approved->value);

        $cancelCase = $this->deliveredOrder();
        Sanctum::actingAs($cancelCase['customer']);
        $cancelled = $this->postJson('/api/v1/user/returns', [
            'order_item_id' => $cancelCase['item']->id,
            'quantity' => 1,
            'reason' => 'Changed mind',
        ])->assertCreated()->json('data');

        Sanctum::actingAs($cancelCase['vendor']->user);
        $this->putJson("/api/v1/vendor/returns/{$cancelled['id']}/status", ['status' => 'CLOSED'])
            ->assertOk()
            ->assertJsonPath('data.status', ReturnStatus::Closed->value);
    }

    public function test_vendor_cannot_update_another_vendors_return(): void
    {
        $c = $this->deliveredOrder();
        $other = VendorProfile::factory()->approved()->create();
        Sanctum::actingAs($c['customer']);
        $return = $this->postJson('/api/v1/user/returns', [
            'order_item_id' => $c['item']->id,
            'reason' => 'Damaged',
        ])->assertCreated()->json('data');

        Sanctum::actingAs($other->user);
        $this->putJson("/api/v1/vendor/returns/{$return['id']}/status", ['status' => 'APPROVED'])
            ->assertStatus(404);
    }

    public function test_return_request_rejected_for_non_returnable_product(): void
    {
        $c = $this->deliveredOrder([], ['is_returnable' => false]);

        Sanctum::actingAs($c['customer']);

        $this->postJson('/api/v1/user/returns', [
            'order_item_id' => $c['item']->id,
            'reason' => 'Damaged',
        ])->assertStatus(422);
    }

    public function test_vendor_can_set_return_policy_when_creating_product(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($vendor->user);

        $this->postJson('/api/v1/vendor/products', [
            'category_id' => $category->id,
            'name' => 'Returnable item',
            'price' => 199,
            'mrp' => 199,
            'stock' => 5,
            'status' => 'DRAFT',
            'is_returnable' => true,
            'return_days' => 14,
            'return_policy' => 'Return this item within 14 days.',
        ])->assertCreated()
            ->assertJsonPath('data.is_returnable', true)
            ->assertJsonPath('data.return_days', 14)
            ->assertJsonPath('data.return_policy', 'Return this item within 14 days.');
    }

    public function test_admin_can_update_product_return_policy(): void
    {
        $c = $this->deliveredOrder();

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->putJson("/api/v1/admin/products/{$c['product']->id}/return-policy", [
            'is_returnable' => false,
            'return_days' => null,
            'return_policy' => 'This product cannot be returned.',
        ])->assertOk()
            ->assertJsonPath('data.is_returnable', false)
            ->assertJsonPath('data.return_policy', 'This product cannot be returned.');

        $this->assertSame('This product cannot be returned.', $c['product']->fresh()->return_policy);
    }
}
