<?php

namespace Tests\Feature;

use App\Enums\CouponErrorCode;
use App\Events\OrderPlaced;
use App\Models\Address;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\Payment\PaymentSettingsService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PricingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([OrderPlaced::class]);

        Http::fake([
            'api.razorpay.com/*' => Http::response([
                'id' => 'order_rzp_test',
                'amount' => 1000,
                'currency' => 'INR',
                'receipt' => 'ORD-TEST',
            ], 200),
        ]);
    }

    protected function addressFor(User $user): Address
    {
        return Address::factory()->create(['user_id' => $user->id]);
    }

    protected function adminCoupon(array $attributes = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'SAVE'.strtoupper(substr(md5((string) microtime()), 0, 6)),
            'type' => 'PERCENTAGE',
            'value' => 10,
            'min_amount' => 0,
            'max_discount' => null,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(10),
            'usage_limit' => 100,
            'per_user_limit' => 1,
            'first_order_only' => false,
            'used_count' => 0,
            'is_active' => true,
        ], $attributes));
    }

    protected function addToCart(User $user, Product $product, int $quantity = 1): TestResponse
    {
        Sanctum::actingAs($user);

        return $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => $quantity])->assertOk();
    }

    protected function validateCoupon(User $user, string $code): TestResponse
    {
        Sanctum::actingAs($user);

        return $this->postJson('/api/v1/user/coupons/validate', ['code' => $code]);
    }

    protected function placeOrder(User $user, array $payload = []): TestResponse
    {
        Sanctum::actingAs($user);

        return $this->postJson('/api/v1/user/orders', array_merge([
            'address_id' => $this->addressFor($user)->id,
            'payment_method' => 'cod',
        ], $payload));
    }

    // ------------------------------------------------------------------
    //  Coupon types
    // ------------------------------------------------------------------

    public function test_percentage_coupon_discount_is_computed_server_side(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 700, 'mrp' => 700, 'gst' => 0, 'stock' => 10]);
        $coupon = $this->adminCoupon(['type' => 'PERCENTAGE', 'value' => 10, 'min_amount' => 500]);

        $this->addToCart($user, $product);

        $this->validateCoupon($user, $coupon->code)
            ->assertOk()
            ->assertJsonPath('data.discount', 70)
            ->assertJsonPath('data.payable', 680)
            ->assertJsonPath('data.coupon.code', $coupon->code);
    }

    public function test_fixed_coupon_discount_is_capped_by_max_discount(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 700, 'mrp' => 700, 'gst' => 0, 'stock' => 10]);
        $coupon = $this->adminCoupon(['type' => 'FIXED', 'value' => 200, 'max_discount' => 100]);

        $this->addToCart($user, $product);

        $this->validateCoupon($user, $coupon->code)
            ->assertOk()
            ->assertJsonPath('data.discount', 100)
            ->assertJsonPath('data.payable', 650);
    }

    public function test_coupon_discount_never_exceeds_order_value(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 300, 'mrp' => 300, 'gst' => 0, 'stock' => 10]);
        $coupon = $this->adminCoupon(['type' => 'FIXED', 'value' => 500, 'max_discount' => null]);

        $this->addToCart($user, $product);

        $this->validateCoupon($user, $coupon->code)
            ->assertOk()
            ->assertJsonPath('data.discount', 300)
            ->assertJsonPath('data.payable', 50);
    }

    // ------------------------------------------------------------------
    //  Coupon conditions
    // ------------------------------------------------------------------

    public function test_expired_coupon_returns_expired_error(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 300, 'stock' => 10]);
        $coupon = $this->adminCoupon(['expires_at' => now()->subDay()]);

        $this->addToCart($user, $product);

        $this->validateCoupon($user, $coupon->code)->assertStatus(422)
            ->assertJsonPath('errors.0.code', CouponErrorCode::CouponExpired->value);
    }

    public function test_inactive_coupon_returns_inactive_error(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 300, 'stock' => 10]);
        $coupon = $this->adminCoupon(['is_active' => false]);

        $this->addToCart($user, $product);

        $this->validateCoupon($user, $coupon->code)->assertStatus(422)
            ->assertJsonPath('errors.0.code', CouponErrorCode::CouponInactive->value);
    }

    public function test_coupon_before_start_date_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 300, 'stock' => 10]);
        $coupon = $this->adminCoupon(['starts_at' => now()->addDay()]);

        $this->addToCart($user, $product);

        $this->validateCoupon($user, $coupon->code)->assertStatus(422)
            ->assertJsonPath('errors.0.code', CouponErrorCode::CouponNotStarted->value);
    }

    public function test_minimum_order_not_met_returns_structured_error(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 300, 'stock' => 10]);
        $coupon = $this->adminCoupon(['min_amount' => 1000]);

        $this->addToCart($user, $product);

        $this->validateCoupon($user, $coupon->code)->assertStatus(422)
            ->assertJsonPath('errors.0.code', CouponErrorCode::MinimumOrderNotMet->value);
    }

    public function test_usage_limit_reached_returns_structured_error(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 300, 'stock' => 10]);
        $coupon = $this->adminCoupon(['usage_limit' => 5, 'used_count' => 5]);

        $this->addToCart($user, $product);

        $this->validateCoupon($user, $coupon->code)->assertStatus(422)
            ->assertJsonPath('errors.0.code', CouponErrorCode::UsageLimitReached->value);
    }

    public function test_per_user_limit_is_enforced(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 300, 'stock' => 10]);
        $coupon = $this->adminCoupon(['per_user_limit' => 2]);

        CouponUsage::factory()->create(['coupon_id' => $coupon->id, 'user_id' => $user->id]);
        CouponUsage::factory()->create(['coupon_id' => $coupon->id, 'user_id' => $user->id]);

        $this->addToCart($user, $product);

        $this->validateCoupon($user, $coupon->code)->assertStatus(422)
            ->assertJsonPath('errors.0.code', CouponErrorCode::UserLimitReached->value);
    }

    public function test_first_order_only_coupon_rejects_returning_customers(): void
    {
        $user = User::factory()->create();
        Order::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['price' => 300, 'stock' => 10]);
        $coupon = $this->adminCoupon(['first_order_only' => true]);

        $this->addToCart($user, $product);

        $this->validateCoupon($user, $coupon->code)->assertStatus(422)
            ->assertJsonPath('errors.0.code', CouponErrorCode::FirstOrderOnly->value);
    }

    // ------------------------------------------------------------------
    //  Coupon restrictions
    // ------------------------------------------------------------------

    public function test_product_restricted_coupon_rejects_unlisted_products(): void
    {
        $user = User::factory()->create();
        $eligible = Product::factory()->create(['price' => 300, 'stock' => 10]);
        $ineligible = Product::factory()->create(['price' => 300, 'stock' => 10]);
        $coupon = $this->adminCoupon();
        $coupon->products()->attach($eligible->id);

        $this->addToCart($user, $ineligible);

        $this->validateCoupon($user, $coupon->code)->assertStatus(422)
            ->assertJsonPath('errors.0.code', CouponErrorCode::ProductNotApplicable->value);
    }

    public function test_category_restricted_coupon_rejects_other_categories(): void
    {
        $user = User::factory()->create();
        $allowed = Category::factory()->create();
        $other = Category::factory()->create();
        $product = Product::factory()->create(['price' => 300, 'category_id' => $other->id, 'stock' => 10]);
        $coupon = $this->adminCoupon();
        $coupon->categories()->attach($allowed->id);

        $this->addToCart($user, $product);

        $this->validateCoupon($user, $coupon->code)->assertStatus(422)
            ->assertJsonPath('errors.0.code', CouponErrorCode::CategoryNotApplicable->value);
    }

    public function test_brand_restricted_coupon_rejects_other_brands(): void
    {
        $user = User::factory()->create();
        $allowed = Brand::factory()->create();
        $other = Brand::factory()->create();
        $product = Product::factory()->create(['price' => 300, 'brand_id' => $other->id, 'stock' => 10]);
        $coupon = $this->adminCoupon();
        $coupon->brands()->attach($allowed->id);

        $this->addToCart($user, $product);

        $this->validateCoupon($user, $coupon->code)->assertStatus(422)
            ->assertJsonPath('errors.0.code', CouponErrorCode::BrandNotApplicable->value);
    }

    public function test_vendor_restricted_coupon_rejects_other_vendors(): void
    {
        $user = User::factory()->create();
        $vendorA = VendorProfile::factory()->approved()->create();
        $vendorB = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['price' => 300, 'vendor_id' => $vendorB->id, 'stock' => 10]);
        $coupon = $this->adminCoupon();
        $coupon->vendors()->attach($vendorA->id);

        $this->addToCart($user, $product);

        $this->validateCoupon($user, $coupon->code)->assertStatus(422)
            ->assertJsonPath('errors.0.code', CouponErrorCode::VendorNotApplicable->value);
    }

    public function test_restricted_coupon_applies_only_to_eligible_lines_in_a_mixed_cart(): void
    {
        $user = User::factory()->create();
        $vendorA = VendorProfile::factory()->approved()->create();
        $vendorB = VendorProfile::factory()->approved()->create();
        $eligible = Product::factory()->create(['vendor_id' => $vendorA->id, 'price' => 500, 'mrp' => 500, 'gst' => 12, 'stock' => 10]);
        $other = Product::factory()->create(['vendor_id' => $vendorB->id, 'price' => 200, 'mrp' => 200, 'gst' => 5, 'stock' => 10]);
        $coupon = $this->adminCoupon(['type' => 'PERCENTAGE', 'value' => 10, 'min_amount' => 0]);
        $coupon->products()->attach($eligible->id);

        $this->addToCart($user, $eligible);
        $this->addToCart($user, $other);

        $response = $this->validateCoupon($user, $coupon->code)->assertOk();

        $response->assertJsonPath('data.discount', 50)
            ->assertJsonPath('data.grand_total', 750)
            ->assertJsonPath('data.payable', 750);

        $groups = $response->json('data.vendor_groups');
        $this->assertEqualsWithDelta(50, collect($groups)->where('vendor_id', $vendorA->id)->first()['coupon_discount'], 0.01);
        $this->assertEqualsWithDelta(0, collect($groups)->where('vendor_id', $vendorB->id)->first()['coupon_discount'], 0.01);
    }

    // ------------------------------------------------------------------
    //  Pricing engine
    // ------------------------------------------------------------------

    public function test_cart_breakdown_sums_per_vendor_shipping_and_tax(): void
    {
        $user = User::factory()->create();
        $vendorA = VendorProfile::factory()->approved()->create();
        $vendorB = VendorProfile::factory()->approved()->create();
        $productA = Product::factory()->create(['vendor_id' => $vendorA->id, 'price' => 500, 'gst' => 12, 'stock' => 10]);
        $productB = Product::factory()->create(['vendor_id' => $vendorB->id, 'price' => 200, 'gst' => 5, 'stock' => 10]);

        $this->addToCart($user, $productA);
        $this->addToCart($user, $productB, 2);

        $this->getJson('/api/v1/user/cart/totals')
            ->assertOk()
            ->assertJsonPath('data.subtotal', 900)
            ->assertJsonPath('data.tax', 72.62)
            ->assertJsonPath('data.shipping', 100)
            ->assertJsonPath('data.total', 1000);
    }

    public function test_product_fees_are_charged_once_per_line_and_not_taxed(): void
    {
        $user = User::factory()->create();
        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 300, 'mrp' => 300, 'gst' => 12, 'stock' => 10]);
        $product->fees()->create(['name' => 'Handling charge', 'amount' => 40]);

        // Two units: the fee is still charged once per line (not × quantity),
        // and tax is extracted from the price only (fees are non-taxable).
        $this->addToCart($user, $product, 2);

        $this->getJson('/api/v1/user/cart/totals')
            ->assertOk()
            ->assertJsonPath('data.subtotal', 600)
            ->assertJsonPath('data.fees_total', 40)
            ->assertJsonPath('data.tax', 64.29)
            ->assertJsonPath('data.shipping', 50)
            ->assertJsonPath('data.total', 690);

        $this->getJson('/api/v1/user/cart')
            ->assertOk()
            ->assertJsonPath('data.fees_total', 40)
            ->assertJsonPath('data.vendor_groups.0.fees_total', 40)
            ->assertJsonPath('data.items.0.fees.0.name', 'Handling charge')
            ->assertJsonPath('data.items.0.fees.0.amount', 40)
            ->assertJsonPath('data.items.0.fees_total', 40);
    }

    public function test_order_persists_product_fees_and_items_snapshot_them(): void
    {
        $user = User::factory()->create();
        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 700, 'mrp' => 700, 'gst' => 0, 'stock' => 10]);
        $product->fees()->create(['name' => 'Handling charge', 'amount' => 50]);

        $this->addToCart($user, $product);

        $this->placeOrder($user)
            ->assertCreated()
            ->assertJsonPath('data.order.subtotal', 700)
            ->assertJsonPath('data.order.fees_total', 50)
            ->assertJsonPath('data.order.shipping_charge', 50)
            ->assertJsonPath('data.order.total', 800)
            ->assertJsonPath('data.order.items.0.fees_total', 50)
            ->assertJsonPath('data.order.items.0.fees.0.name', 'Handling charge');

        $order = Order::sole();
        $this->assertEqualsWithDelta(50, (float) $order->fees_total, 0.01);
        $this->assertEqualsWithDelta(50, (float) $order->vendorOrders()->sole()->fees_total, 0.01);
    }

    public function test_global_free_delivery_applies_when_subtotal_reaches_threshold(): void
    {
        app(PaymentSettingsService::class)->update(['free_delivery_threshold' => 800]);

        $user = User::factory()->create();
        $vendorA = VendorProfile::factory()->approved()->create();
        $vendorB = VendorProfile::factory()->approved()->create();
        $productA = Product::factory()->create(['vendor_id' => $vendorA->id, 'price' => 500, 'gst' => 12, 'stock' => 10]);
        $productB = Product::factory()->create(['vendor_id' => $vendorB->id, 'price' => 200, 'gst' => 5, 'stock' => 10]);

        $this->addToCart($user, $productA);
        $this->addToCart($user, $productB, 2);

        $this->getJson('/api/v1/user/cart/totals')
            ->assertOk()
            ->assertJsonPath('data.subtotal', 900)
            ->assertJsonPath('data.shipping', 0)
            ->assertJsonPath('data.total', 900);

        $this->placeOrder($user)
            ->assertCreated()
            ->assertJsonPath('data.order.shipping_charge', 0)
            ->assertJsonPath('data.order.total', 900);

        app(PaymentSettingsService::class)->update(['free_delivery_threshold' => null]);
    }

    public function test_shipping_is_still_charged_below_global_threshold(): void
    {
        app(PaymentSettingsService::class)->update(['free_delivery_threshold' => 1000]);

        $user = User::factory()->create();
        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 300, 'mrp' => 300, 'gst' => 0, 'stock' => 10]);

        $this->addToCart($user, $product);

        $this->getJson('/api/v1/user/cart/totals')
            ->assertOk()
            ->assertJsonPath('data.subtotal', 300)
            ->assertJsonPath('data.shipping', 50)
            ->assertJsonPath('data.total', 350);

        app(PaymentSettingsService::class)->update(['free_delivery_threshold' => null]);
    }

    public function test_order_persists_breakdown_and_allocates_coupon_across_vendors(): void
    {
        $user = User::factory()->create();
        $vendorA = VendorProfile::factory()->approved()->create();
        $vendorB = VendorProfile::factory()->approved()->create();
        $productA = Product::factory()->create(['vendor_id' => $vendorA->id, 'price' => 500, 'mrp' => 500, 'gst' => 12, 'stock' => 10]);
        $productB = Product::factory()->create(['vendor_id' => $vendorB->id, 'price' => 200, 'mrp' => 200, 'gst' => 5, 'stock' => 10]);
        $coupon = $this->adminCoupon(['type' => 'PERCENTAGE', 'value' => 10]);

        $this->addToCart($user, $productA);
        $this->addToCart($user, $productB, 2);

        $response = $this->placeOrder($user, ['coupon_code' => $coupon->code]);

        $response->assertCreated()
            ->assertJsonPath('data.order.subtotal', 900)
            ->assertJsonPath('data.order.discount', 90)
            ->assertJsonPath('data.order.tax', 72.62)
            ->assertJsonPath('data.order.shipping_charge', 100)
            ->assertJsonPath('data.order.total', 910);

        $this->assertSame(1, CouponUsage::where('coupon_id', $coupon->id)->where('user_id', $user->id)->count());
        $this->assertSame(1, $coupon->refresh()->used_count);

        $order = Order::sole();
        $vendorOrders = $order->vendorOrders;

        $this->assertEqualsWithDelta(910, (float) $vendorOrders->sum('total'), 0.01);
        $this->assertEqualsWithDelta(90, (float) $vendorOrders->sum('discount'), 0.01);

        $groupA = $vendorOrders->where('vendor_id', $vendorA->id)->first();
        $groupB = $vendorOrders->where('vendor_id', $vendorB->id)->first();

        $this->assertEqualsWithDelta(500, (float) $groupA->total, 0.01);
        $this->assertEqualsWithDelta(410, (float) $groupB->total, 0.01);
        $this->assertEqualsWithDelta(50, (float) $groupA->discount, 0.01);
        $this->assertEqualsWithDelta(40, (float) $groupB->discount, 0.01);
    }

    public function test_cod_method_adds_cod_charge(): void
    {
        config()->set('shipping.cod_charge', 50);

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 700, 'mrp' => 700, 'gst' => 0, 'stock' => 10]);

        $this->addToCart($user, $product);

        $this->getJson('/api/v1/user/cart/totals')
            ->assertOk()
            ->assertJsonPath('data.cod_charge', 0)
            ->assertJsonPath('data.total', 750);

        $response = $this->placeOrder($user, ['payment_method' => 'cod']);

        $response->assertCreated()
            ->assertJsonPath('data.order.cod_charge', 50)
            ->assertJsonPath('data.order.total', 800);
    }

    public function test_wallet_payment_reduces_payable_and_debits_wallet(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 1000, 'test credit');

        $product = Product::factory()->create(['price' => 700, 'mrp' => 700, 'gst' => 0, 'stock' => 10]);

        $this->addToCart($user, $product);

        $response = $this->placeOrder($user, ['payment_method' => 'wallet']);

        $response->assertCreated()
            ->assertJsonPath('data.order.wallet_amount', 750)
            ->assertJsonPath('data.order.total', 0);

        $this->assertEqualsWithDelta(250, app(WalletService::class)->balance($user), 0.01);
    }

    public function test_wallet_payment_fails_without_sufficient_balance(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 700, 'mrp' => 700, 'gst' => 0, 'stock' => 10]);

        $this->addToCart($user, $product);

        $this->placeOrder($user, ['payment_method' => 'wallet'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient wallet balance to complete the order.');
    }

    public function test_partial_wallet_amount_is_capped_at_balance(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 100, 'test credit');

        $product = Product::factory()->create(['price' => 700, 'mrp' => 700, 'gst' => 0, 'stock' => 10]);

        $this->addToCart($user, $product);

        $this->placeOrder($user, ['payment_method' => 'razorpay', 'wallet_amount' => 500])
            ->assertCreated()
            ->assertJsonPath('data.order.wallet_amount', 100)
            ->assertJsonPath('data.order.total', 650);
    }
}
