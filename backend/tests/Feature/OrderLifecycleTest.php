<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\VendorOrderStatus;
use App\Events\OrderPlaced;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorOrder;
use App\Models\VendorOrderItem;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
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
                'receipt' => 'ORD-TEST',
            ], 200),
        ]);
    }

    protected function placeOrder(User $user, Address $address): void
    {
        $this->postJson('/api/v1/user/orders', [
            'address_id' => $address->id,
            'payment_method' => 'cod',
        ])->assertCreated();
    }

    public function test_vendor_can_advance_its_sub_order_and_master_follows(): void
    {
        $vendorA = VendorProfile::factory()->approved()->create();
        $vendorB = VendorProfile::factory()->approved()->create();

        $productA = Product::factory()->create(['vendor_id' => $vendorA->id, 'price' => 300, 'mrp' => 300, 'gst' => 0, 'stock' => 5]);
        $productB = Product::factory()->create(['vendor_id' => $vendorB->id, 'price' => 150, 'mrp' => 150, 'gst' => 0, 'stock' => 5]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $productA->id, 'quantity' => 1])->assertOk();
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $productB->id, 'quantity' => 1])->assertOk();

        $this->placeOrder($customer, Address::factory()->create(['user_id' => $customer->id]));

        $order = Order::first();
        $vendorOrderA = $order->vendorOrders()->where('vendor_id', $vendorA->id)->first();
        $vendorOrderB = $order->vendorOrders()->where('vendor_id', $vendorB->id)->first();

        $this->assertSame(OrderStatus::Pending->value, $order->order_status);

        Sanctum::actingAs($vendorA->user);

        $this->putJson("/api/v1/vendor/orders/{$vendorOrderA->id}/status", ['status' => 'CONFIRMED'])
            ->assertOk()
            ->assertJsonPath('data.vendor_order.status', VendorOrderStatus::Confirmed->value)
            ->assertJsonPath('data.vendor_order.status_label', 'Confirmed')
            ->assertJsonPath('data.vendor_order.order.order_status', OrderStatus::Confirmed->value)
            ->assertJsonPath('data.vendor_order.items.0.status', VendorOrderStatus::Confirmed->value)
            ->assertJsonPath('data.vendor_order.vendor_order_number', $vendorOrderA->vendor_order_number)
            ->assertJsonPath('data.vendor_order.order.customer.email', $customer->email);

        $this->assertNotNull($vendorOrderA->fresh()->confirmed_at);
        $this->assertSame(VendorOrderStatus::Pending->value, $vendorOrderB->fresh()->status);
        $this->assertSame(OrderStatus::Confirmed->value, $order->fresh()->order_status);

        $history = OrderStatusHistory::query()
            ->where('historable_type', VendorOrder::class)
            ->where('historable_id', $vendorOrderA->id)
            ->orderBy('id')->get();

        $this->assertSame(
            [OrderStatus::Pending->value, VendorOrderStatus::Confirmed->value],
            $history->pluck('to_status')->all(),
        );
        $this->assertSame(VendorOrderStatus::Pending->value, $history->last()->from_status);
    }

    public function test_invalid_sub_order_transition_is_rejected(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 200, 'mrp' => 200, 'gst' => 0, 'stock' => 5]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $this->placeOrder($customer, Address::factory()->create(['user_id' => $customer->id]));

        $vendorOrder = Order::first()->vendorOrders()->first();

        Sanctum::actingAs($vendor->user);

        $this->putJson("/api/v1/vendor/orders/{$vendorOrder->id}/status", ['status' => 'CONFIRMED'])->assertOk();
        $this->putJson("/api/v1/vendor/orders/{$vendorOrder->id}/status", ['status' => 'PENDING'])
            ->assertStatus(422);
    }

    public function test_vendor_cannot_touch_another_vendors_order(): void
    {
        $vendorA = VendorProfile::factory()->approved()->create();
        $vendorB = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendorA->id, 'price' => 200, 'mrp' => 200, 'gst' => 0, 'stock' => 5]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $this->placeOrder($customer, Address::factory()->create(['user_id' => $customer->id]));

        $vendorOrder = Order::first()->vendorOrders()->first();

        Sanctum::actingAs($vendorB->user);

        $this->putJson("/api/v1/vendor/orders/{$vendorOrder->id}/status", ['status' => 'CONFIRMED'])
            ->assertStatus(404);
    }

    public function test_fulfilment_transition_syncs_items_until_terminal_state(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 200, 'mrp' => 200, 'gst' => 0, 'stock' => 5]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();

        $this->postJson('/api/v1/user/orders', [
            'address_id' => Address::factory()->create(['user_id' => $customer->id])->id,
            'payment_method' => 'cod',
        ])->assertCreated();

        $order = Order::first();
        $vendorOrder = $order->vendorOrders()->first();
        $orderItem = OrderItem::first();

        $this->assertSame(OrderStatus::Pending->value, $orderItem->status);
        $this->assertSame(OrderStatus::Pending->value, VendorOrderItem::first()->status);

        Sanctum::actingAs($vendor->user);

        foreach (['PROCESSING', 'PACKED', 'READY_TO_SHIP', 'SHIPPED', 'OUT_FOR_DELIVERY', 'DELIVERED'] as $step) {
            $this->putJson("/api/v1/vendor/orders/{$vendorOrder->id}/status", ['status' => $step])->assertOk();
        }

        $this->assertSame(VendorOrderStatus::Delivered->value, $vendorOrder->fresh()->status);
        $this->assertSame(VendorOrderStatus::Delivered->value, $orderItem->fresh()->status);
        $this->assertSame(VendorOrderStatus::Delivered->value, VendorOrderItem::first()->fresh()->status);
        $this->assertSame(OrderStatus::Delivered->value, $order->fresh()->order_status);

        $this->putJson("/api/v1/vendor/orders/{$vendorOrder->id}/status", ['status' => 'CANCELLED'])
            ->assertStatus(422);

        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_admin_can_list_and_update_vendor_orders_and_cancel_master_order(): void
    {
        $vendorA = VendorProfile::factory()->approved()->create();
        $vendorB = VendorProfile::factory()->approved()->create();
        $productA = Product::factory()->create(['vendor_id' => $vendorA->id, 'price' => 300, 'mrp' => 300, 'gst' => 0, 'stock' => 5]);
        $productB = Product::factory()->create(['vendor_id' => $vendorB->id, 'price' => 150, 'mrp' => 150, 'gst' => 0, 'stock' => 5]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $productA->id, 'quantity' => 1])->assertOk();
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $productB->id, 'quantity' => 1])->assertOk();
        $this->placeOrder($customer, Address::factory()->create(['user_id' => $customer->id]));

        $order = Order::first();
        $vendorOrderA = $order->vendorOrders()->where('vendor_id', $vendorA->id)->first();

        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/vendor-orders')
            ->assertOk()
            ->assertJsonCount(2, 'data.data');

        $this->putJson("/api/v1/admin/vendor-orders/{$vendorOrderA->id}/status", ['status' => 'CONFIRMED'])
            ->assertOk()
            ->assertJsonPath('data.vendor_order.status', VendorOrderStatus::Confirmed->value);

        $this->getJson("/api/v1/admin/vendor-orders/{$vendorOrderA->id}")
            ->assertOk()
            ->assertJsonPath('data.vendor_order.status', VendorOrderStatus::Confirmed->value);

        $this->putJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'CANCELLED'])
            ->assertOk()
            ->assertJsonPath('data.order.order_status', OrderStatus::Cancelled->value);

        $this->assertNotNull($order->fresh()->cancelled_at);
    }
}
