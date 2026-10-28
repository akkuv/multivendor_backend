<?php

namespace Tests\Feature;

use App\Events\OrderPlaced;
use App\Models\Address;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\Cart\CartService;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function vendorUser(): User
    {
        $user = User::factory()->vendor()->create();
        VendorProfile::factory()->for($user)->create();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function vendorProduct(User $user, array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'vendor_id' => $user->vendorProfile->id,
        ], $attributes));
    }

    protected function adminUser(): User
    {
        $user = User::factory()->superAdmin()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_vendor_can_create_product_with_variants_images_and_videos(): void
    {
        $this->vendorUser();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        $this->postJson('/api/v1/vendor/products', [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Slim Fit T-Shirt',
            'description' => 'Comfortable cotton tee.',
            'images' => [
                ['url' => 'https://img.example.com/slim-front.jpg', 'is_primary' => true, 'sort_order' => 0],
                ['url' => 'https://img.example.com/slim-back.jpg', 'sort_order' => 1],
            ],
            'videos' => [
                ['url' => 'https://videos.example.com/slim.mp4', 'provider' => 'youtube', 'sort_order' => 0],
            ],
            'variants' => [
                ['attributes' => ['Size' => 'M', 'Color' => 'Black'], 'price' => 499, 'mrp' => 699, 'stock' => 10, 'sku' => 'TS-BLK-M'],
                ['attributes' => ['Size' => 'L', 'Color' => 'Black'], 'price' => 549, 'mrp' => 749, 'stock' => 5, 'sku' => 'TS-BLK-L'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Slim Fit T-Shirt')
            ->assertJsonPath('data.approval_status', 'PENDING_APPROVAL')
            ->assertJsonCount(2, 'data.variants')
            ->assertJsonCount(2, 'data.images')
            ->assertJsonCount(1, 'data.videos')
            ->assertJsonPath('data.images.0', 'https://img.example.com/slim-front.jpg');

        $product = Product::where('name', 'Slim Fit T-Shirt')->firstOrFail();

        $this->assertSame(499.0, (float) $product->price);
        $this->assertSame(
            ['https://img.example.com/slim-front.jpg', 'https://img.example.com/slim-back.jpg'],
            $product->images,
        );
        $this->assertSame(2, $product->images()->count());
        $this->assertSame(1, $product->videos()->count());
        $this->assertSame(2, $product->variants()->count());
    }

    public function test_vendor_can_store_a_long_short_description(): void
    {
        // The column used to be a VARCHAR(255) while the form allows 500 chars, so
        // saving a longer summary failed with SQLSTATE[22001] "Data too long".
        $this->assertSame('text', Schema::getColumnType('products', 'short_description'));

        $this->vendorUser();
        $category = Category::factory()->create();
        $shortDescription = str_repeat('Occasion-ready kurta set. ', 19);

        $this->assertGreaterThan(255, strlen($shortDescription));
        $this->assertLessThanOrEqual(500, strlen($shortDescription));

        $this->postJson('/api/v1/vendor/products', [
            'category_id' => $category->id,
            'name' => 'Festive Kurta Set',
            'short_description' => $shortDescription,
            'price' => 2499,
        ])->assertCreated();

        $this->assertSame(
            trim($shortDescription),
            Product::query()->where('name', 'Festive Kurta Set')->firstOrFail()->short_description,
        );
    }

    public function test_vendor_can_create_and_update_product_fees(): void
    {
        $user = $this->vendorUser();
        $category = Category::factory()->create();

        $this->postJson('/api/v1/vendor/products', [
            'category_id' => $category->id,
            'name' => 'Handling Test Product',
            'price' => 399,
            'stock' => 10,
            'fees' => [
                ['name' => 'Handling charge', 'amount' => '49'],
                ['name' => 'Packaging', 'amount' => '10'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Handling Test Product')
            ->assertJsonCount(2, 'data.fees');

        $product = Product::where('name', 'Handling Test Product')->firstOrFail();

        $this->assertSame(2, $product->fees()->count());
        $this->assertSame('Handling charge', $product->fees()->orderBy('id')->first()->name);
        $this->assertSame(49.0, (float) $product->fees()->orderBy('id')->first()->amount);

        // Updating replaces the whole fee set, mirroring the other syncs.
        $this->putJson("/api/v1/vendor/products/{$product->id}", [
            'fees' => [
                ['name' => 'Handling charge', 'amount' => '59'],
            ],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.fees');

        $product->refresh();
        $this->assertSame(59.0, (float) $product->fees()->sole()->amount);
        $this->assertSame(1, $product->fees()->count());
    }

    public function test_vendor_product_fees_are_validated(): void
    {
        $this->vendorUser();
        $category = Category::factory()->create();

        $this->postJson('/api/v1/vendor/products', [
            'category_id' => $category->id,
            'name' => 'Invalid Fees Product',
            'price' => 100,
            'stock' => 10,
            'fees' => [
                ['name' => '', 'amount' => '-5'],
            ],
        ])->assertStatus(422);
    }

    public function test_vendor_cannot_create_duplicate_variant_combination(): void
    {
        $this->vendorUser();
        $category = Category::factory()->create();

        $this->postJson('/api/v1/vendor/products', [
            'category_id' => $category->id,
            'name' => 'Duplicate Variants',
            'variants' => [
                ['attributes' => ['Size' => 'M', 'Color' => 'Black'], 'price' => 499, 'stock' => 10],
                ['attributes' => ['Color' => 'Black', 'Size' => 'M'], 'price' => 499, 'stock' => 5],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('variants');
    }

    public function test_vendor_can_update_product_and_remove_variants(): void
    {
        $user = $this->vendorUser();
        $product = $this->vendorProduct($user, ['stock' => 20]);
        $product->variants()->create([
            'sku' => 'A1',
            'price' => 100,
            'attributes' => ['Size' => 'S'],
            'combination_hash' => sha1('Size=S'),
        ]);
        $product->variants()->create([
            'sku' => 'A2',
            'price' => 120,
            'attributes' => ['Size' => 'L'],
            'combination_hash' => sha1('Size=L'),
        ]);

        $this->putJson("/api/v1/vendor/products/{$product->id}", [
            'name' => 'Renamed',
            'variants' => [
                ['id' => $product->variants()->first()->id, 'attributes' => ['Size' => 'S'], 'price' => 110, 'stock' => 8],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonCount(1, 'data.variants');

        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'price' => 110.0]);
    }

    public function test_storefront_only_shows_live_products_and_admin_can_approve_or_reject(): void
    {
        $this->adminUser();
        $pending = Product::factory()->pending()->create();
        $approved = Product::factory()->create();
        $rejected = Product::factory()->rejected()->create();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $approved->id);

        $this->getJson("/api/v1/products/{$pending->id}")->assertNotFound();
        $this->getJson("/api/v1/products/{$rejected->id}")->assertNotFound();

        $this->putJson("/api/v1/admin/products/{$pending->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'APPROVED')
            ->assertJsonPath('data.status', 'PUBLISHED');

        $this->getJson("/api/v1/products/{$pending->id}")->assertOk();

        $this->putJson("/api/v1/admin/products/{$approved->id}/reject", ['reason' => 'Missing tax details.'])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'REJECTED');
    }

    public function test_vendor_editing_rejected_product_resubmits_for_approval(): void
    {
        $user = $this->vendorUser();
        $product = $this->vendorProduct($user)->fresh();
        $product->update([
            'approval_status' => 'REJECTED',
            'status' => 'DRAFT',
            'rejected_at' => now()->subDay(),
            'rejection_reason' => 'Duplicate listing.',
        ]);

        $this->putJson("/api/v1/vendor/products/{$product->id}", [
            'description' => 'Updated description.',
        ])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'PENDING_APPROVAL')
            ->assertJsonPath('data.rejection_reason', null);
    }

    public function test_admin_can_filter_products_by_approval_status(): void
    {
        $this->adminUser();
        Product::factory()->pending()->create();
        Product::factory()->create();

        $this->getJson('/api/v1/admin/products?approval_status=PENDING_APPROVAL')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_vendor_can_adjust_stock_and_view_transactions(): void
    {
        $user = $this->vendorUser();
        $product = $this->vendorProduct($user, ['stock' => 10]);

        $this->postJson("/api/v1/vendor/products/{$product->id}/inventory/adjust", [
            'type' => 'ADD',
            'quantity' => 5,
            'reason' => 'Restock from warehouse.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'ADD')
            ->assertJsonPath('data.stock_before', 10)
            ->assertJsonPath('data.stock_after', 15);

        $this->assertDatabaseHas('inventory_transactions', ['product_id' => $product->id, 'type' => 'ADD', 'quantity' => 5]);

        $this->postJson("/api/v1/vendor/products/{$product->id}/inventory/adjust", [
            'type' => 'DAMAGED',
            'quantity' => 20,
        ])->assertStatus(422);

        $this->getJson("/api/v1/vendor/products/{$product->id}/inventory/transactions")
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_vendor_inventory_adjust_requires_variant_on_variant_products(): void
    {
        $user = $this->vendorUser();
        $product = $this->vendorProduct($user, ['stock' => 0]);
        $product->variants()->create([
            'sku' => 'V1',
            'price' => 100,
            'attributes' => ['Size' => 'M'],
            'combination_hash' => sha1('Size=M'),
            'stock' => 10,
        ]);

        $this->postJson("/api/v1/vendor/products/{$product->id}/inventory/adjust", [
            'type' => 'ADD',
            'quantity' => 5,
        ])->assertStatus(422)->assertJsonPath('message', 'This product uses variants. Adjust stock on a specific variant instead.');

        $variant = $product->variants()->first();

        $this->postJson("/api/v1/vendor/products/{$product->id}/inventory/adjust", [
            'variant_id' => $variant->id,
            'type' => 'ADD',
            'quantity' => 5,
        ])->assertCreated();
    }

    public function test_order_reserves_stock_and_cancel_releases_it(): void
    {
        Event::fake([OrderPlaced::class]);

        $customer = User::factory()->customer()->create();
        $product = Product::factory()->create(['stock' => 10]);
        $address = Address::factory()->for($customer)->create();

        Sanctum::actingAs($customer);

        $cartService = app(CartService::class);
        $cartService->getOrCreateForUser($customer);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 3])->assertOk();

        $order = app(OrderService::class)->createFromCart($customer, $address->id);
        $order->refresh();

        $product->refresh();
        $this->assertSame(3, $product->reserved_stock);
        $this->assertSame(7, $product->availableStock());
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id,
            'type' => 'RESERVE',
            'quantity' => -3,
            'reference_type' => OrderItem::class,
            'reference_id' => $order->items()->first()->id,
        ]);

        app(OrderService::class)->cancel($customer, $order, 'Changed my mind');

        $product->refresh();
        $this->assertSame(0, $product->reserved_stock);
        $this->assertSame(10, $product->availableStock());
        $this->assertSame(Order::where('id', $order->id)->value('order_status'), 'CANCELLED');
    }

    public function test_vendor_cannot_manage_another_vendors_inventory(): void
    {
        $this->vendorUser();
        $other = Product::factory()->create(['stock' => 5]);

        $this->postJson("/api/v1/vendor/products/{$other->id}/inventory/adjust", [
            'type' => 'ADD',
            'quantity' => 5,
        ])->assertNotFound();

        $this->getJson("/api/v1/vendor/products/{$other->id}/inventory/transactions")->assertNotFound();
    }

    public function test_vendor_can_manage_highlights_and_manufacturer_info(): void
    {
        $user = $this->vendorUser();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        $manufacturer = [
            'manufacturer' => 'Waplia Apparel Co.',
            'manufacturer_address' => 'MG Road, Bengaluru 560001',
            'packer' => 'Waplia Packing Pvt Ltd',
            'packer_address' => 'Sector 18, Noida 201301',
            'country_of_origin' => 'India',
        ];

        $payload = [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Highlights Tee',
            'price' => 499,
            'highlights' => [
                'Premium combed cotton jersey',
                'Regular fit with ribbed collar',
                'Machine washable at 30°C',
            ],
            'manufacturer_info' => $manufacturer,
        ];

        $this->postJson('/api/v1/vendor/products', $payload)
            ->assertCreated()
            ->assertJsonPath('data.highlights', $payload['highlights'])
            ->assertJsonPath('data.manufacturer_info', $manufacturer);

        $product = Product::where('name', 'Highlights Tee')->firstOrFail();
        $this->assertSame($payload['highlights'], $product->highlights);
        $this->assertSame($manufacturer, $product->manufacturer_info);

        $this->putJson("/api/v1/vendor/products/{$product->id}", [
            'highlights' => ['Updated highlight one', 'Updated highlight two'],
            'manufacturer_info' => ['manufacturer' => 'Updated Maker', 'country_of_origin' => 'India'],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data.highlights')
            ->assertJsonPath('data.manufacturer_info.manufacturer', 'Updated Maker');
    }

    public function test_storefront_product_offers_endpoint_returns_applicable_coupons(): void
    {
        $vendor = VendorProfile::factory()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $product = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'is_active' => true,
            'status' => 'PUBLISHED',
            'approval_status' => 'APPROVED',
        ]);

        $sitewide = Coupon::factory()->create([
            'code' => 'SITEWIDE',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(10),
        ]);

        $storeCoupon = Coupon::factory()->create([
            'code' => 'STOREONLY',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(10),
        ]);
        $storeCoupon->vendors()->sync([$product->vendor_id]);

        $categoryCoupon = Coupon::factory()->create([
            'code' => 'CATEGORY',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(10),
        ]);
        $categoryCoupon->categories()->sync([$category->id]);

        $otherCoupon = Coupon::factory()->create([
            'code' => 'SOMEONELSE',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(10),
        ]);
        $otherCoupon->brands()->sync([Brand::factory()->create()->id]);

        $expired = Coupon::factory()->create([
            'code' => 'EXPIRED',
            'is_active' => true,
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);

        $inactive = Coupon::factory()->create([
            'code' => 'INACTIVE5',
            'is_active' => false,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(10),
        ]);

        $this->getJson("/api/v1/products/{$product->id}/offers")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['code' => 'SITEWIDE'])
            ->assertJsonFragment(['code' => 'STOREONLY'])
            ->assertJsonFragment(['code' => 'CATEGORY'])
            ->assertJsonMissing(['code' => 'SOMEONELSE'])
            ->assertJsonMissing(['code' => 'EXPIRED'])
            ->assertJsonMissing(['code' => 'INACTIVE5']);
    }
}
