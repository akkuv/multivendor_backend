<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Events\OrderPlaced;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VendorProfile;
use App\Notifications\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCreationTest extends TestCase
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

    public function test_guest_cannot_place_order(): void
    {
        $this->postJson('/api/v1/user/orders', ['address_id' => 1])->assertUnauthorized();
    }

    public function test_checkout_creates_master_order_with_recomputed_totals_and_vendor_split(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $vendorA = VendorProfile::factory()->approved()->create();
        $vendorB = VendorProfile::factory()->approved()->create();
        $productA = Product::factory()->create(['vendor_id' => $vendorA->id, 'price' => 500, 'mrp' => 900, 'gst' => 12, 'stock' => 10]);
        $productB = Product::factory()->create(['vendor_id' => $vendorB->id, 'price' => 200, 'mrp' => 300, 'gst' => 5, 'stock' => 5]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $productA->id, 'quantity' => 1])->assertOk();
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $productB->id, 'quantity' => 2])->assertOk();

        $response = $this->placeOrder($user, $this->addressFor($user));

        $response->assertCreated()
            ->assertJsonPath('data.order.subtotal', 900)
            ->assertJsonPath('data.order.discount', 600)
            ->assertJsonPath('data.order.tax', 72.62)
            ->assertJsonPath('data.order.shipping_charge', 100)
            ->assertJsonPath('data.order.total', 1000)
            ->assertJsonCount(2, 'data.order.items');

        $this->assertSame(1, Order::count());
        $this->assertSame(2, OrderItem::count());
        $this->assertSame(0, CartItem::count());

        $order = Order::first();
        $this->assertSame(2, $order->vendorOrders()->count());

        $vendorOrderA = $order->vendorOrders()->where('vendor_id', $vendorA->id)->first();
        $vendorOrderB = $order->vendorOrders()->where('vendor_id', $vendorB->id)->first();

        $this->assertSame(500.0, (float) $vendorOrderA->subtotal);
        $this->assertSame(50.0, (float) $vendorOrderA->shipping_charge);
        $this->assertSame(550.0, (float) $vendorOrderA->total);
        $this->assertSame(400.0, (float) $vendorOrderB->subtotal);
        $this->assertSame(50.0, (float) $vendorOrderB->shipping_charge);
        $this->assertSame(450.0, (float) $vendorOrderB->total);
        $itemA = $order->items->where('vendor_id', $vendorA->id)->first();
        $itemB = $order->items->where('vendor_id', $vendorB->id)->first();

        $this->assertSame(500.0, (float) $itemA->unit_price);
        $this->assertSame(500.0, (float) $itemA->total);
        $this->assertSame(200.0, (float) $itemB->unit_price);
        $this->assertSame(400.0, (float) $itemB->total);

        $this->assertNotNull($itemA->commission_rate);
        $this->assertGreaterThan(0, (float) $itemA->commission_amount);

        $productA->refresh();
        $productB->refresh();
        $this->assertSame(1, $productA->reserved_stock);
        $this->assertSame(2, $productB->reserved_stock);
    }

    public function test_order_placement_notifies_the_customer_and_each_vendor(): void
    {
        $user = User::factory()->customer()->create();
        Sanctum::actingAs($user);

        $vendorA = VendorProfile::factory()->approved()->create();
        $vendorB = VendorProfile::factory()->approved()->create();
        $productA = Product::factory()->create(['vendor_id' => $vendorA->id, 'price' => 500, 'mrp' => 900, 'gst' => 12, 'stock' => 10]);
        $productB = Product::factory()->create(['vendor_id' => $vendorB->id, 'price' => 200, 'mrp' => 300, 'gst' => 5, 'stock' => 5]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $productA->id, 'quantity' => 1])->assertOk();
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $productB->id, 'quantity' => 1])->assertOk();

        $this->placeOrder($user, $this->addressFor($user))->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $user->id,
            'data->type' => NotificationType::OrderCreated->value,
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $vendorA->user->id,
            'data->type' => NotificationType::NewOrderForVendor->value,
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $vendorB->user->id,
            'data->type' => NotificationType::NewOrderForVendor->value,
        ]);
    }

    public function test_checkout_preserves_variant_price(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create(['price' => 100, 'stock' => 0]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 250, 'stock' => 4]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2])
            ->assertOk();

        $response = $this->placeOrder($user, $this->addressFor($user));

        $response->assertCreated()
            ->assertJsonPath('data.order.subtotal', 500)
            ->assertJsonPath('data.order.items.0.unit_price', 250);

        $this->assertSame(250.0, (float) OrderItem::first()->unit_price);
    }

    public function test_checkout_applies_coupon_and_recomputes_total(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create(['price' => 700, 'mrp' => 700, 'gst' => 0, 'stock' => 10]);
        $coupon = Coupon::factory()->fixed()->create([
            'code' => 'SAVE100',
            'value' => 100,
            'min_amount' => 0,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $response = $this->placeOrder($user, $this->addressFor($user), ['coupon_code' => 'SAVE100']);

        $response->assertCreated()
            ->assertJsonPath('data.order.subtotal', 700)
            ->assertJsonPath('data.order.discount', 100)
            ->assertJsonPath('data.order.tax', 0)
            ->assertJsonPath('data.order.shipping_charge', 50)
            ->assertJsonPath('data.order.total', 650);

        $this->assertSame(1, CouponUsage::where('coupon_id', $coupon->id)->count());
        $this->assertSame(1, $coupon->refresh()->used_count);
    }

    public function test_checkout_revalidates_cart_and_rejects_inactive_products(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create(['price' => 300, 'stock' => 10]);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $this->assertSame(1, CartItem::count());

        $product->forceFill(['is_active' => false])->save();

        $this->placeOrder($user, $this->addressFor($user))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Your cart is empty.');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, CartItem::count());
    }

    public function test_checkout_rejects_empty_cart(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->placeOrder($user, $this->addressFor($user))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Your cart is empty.');
    }

    public function test_razorpay_checkout_returns_configured_key_from_settings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $configuredKey = 'rzp_test_configured_key';
        PaymentSetting::query()->firstOrCreate(['id' => 1])->update([
            'razorpay_enabled' => true,
            'razorpay_key_id' => $configuredKey,
        ]);

        $product = Product::factory()->create(['price' => 200, 'mrp' => 200, 'gst' => 0, 'stock' => 3]);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $response = $this->placeOrder($user, $this->addressFor($user), ['payment_method' => 'razorpay']);

        $response->assertCreated()
            ->assertJsonPath('data.payment.key', $configuredKey)
            ->assertJsonPath('data.payment.order_id', 'order_rzp_test')
            ->assertJsonPath('data.payment.amount', 25000);

        $this->assertDatabaseHas('orders', ['payment_method' => 'razorpay']);
    }

    public function test_checkout_clamps_quantity_to_available_stock(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create(['price' => 200, 'mrp' => 200, 'gst' => 0, 'stock' => 3]);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();

        $product->forceFill(['stock' => 1])->save();

        $response = $this->placeOrder($user, $this->addressFor($user));

        $response->assertCreated()
            ->assertJsonPath('data.order.subtotal', 200)
            ->assertJsonPath('data.order.items.0.quantity', 1)
            ->assertJsonPath('data.order.total', 250);

        $this->assertSame(1, OrderItem::first()->quantity);
        $this->assertSame(0, CartItem::count());
    }
}
