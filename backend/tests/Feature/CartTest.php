<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_cart(): void
    {
        $this->getJson('/api/v1/user/cart')->assertUnauthorized();
    }

    public function test_user_can_add_and_view_cart_item(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create(['price' => 500, 'mrp' => 900, 'gst' => 12, 'stock' => 10]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk();

        $response = $this->getJson('/api/v1/user/cart');

        $response->assertOk()
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.subtotal', 1000)
            ->assertJsonPath('data.tax', 107.14)
            ->assertJsonPath('data.shipping', 50)
            ->assertJsonPath('data.total', 1050)
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.unit_price', 500);
    }

    public function test_adding_same_product_merges_quantity(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create(['stock' => 10]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 3])->assertOk();

        $this->assertSame(1, CartItem::count());
        $this->assertSame(5, CartItem::first()->quantity);
    }

    public function test_variants_are_separate_cart_lines(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create();
        $small = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 300, 'stock' => 10]);
        $large = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 500, 'stock' => 10]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'variant_id' => $small->id])->assertOk();
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'variant_id' => $large->id])->assertOk();

        $this->assertSame(2, CartItem::count());
        $this->assertSame(300.0, (float) CartItem::where('product_variant_id', $small->id)->first()->unit_price);
    }

    public function test_add_uses_product_variant_pricing_when_no_variant_requested(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create(['price' => 999, 'stock' => 0]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 400, 'stock' => 5]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id])
            ->assertOk();

        $this->assertSame(400.0, (float) CartItem::first()->unit_price);
    }

    public function test_add_fails_when_quantity_exceeds_stock(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create(['stock' => 2]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 5])
            ->assertStatus(422);
    }

    public function test_cannot_add_non_live_product(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create(['is_active' => false]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id])->assertStatus(422);
    }

    public function test_cannot_add_product_from_unapproved_vendor(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $vendor = VendorProfile::factory()->create(['is_approved' => false, 'status' => 'PENDING']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id])->assertStatus(422);
    }

    public function test_update_quantity_increases_and_decreases(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create(['stock' => 10]);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();

        $item = CartItem::first();

        $this->putJson("/api/v1/user/cart/items/{$item->id}", ['quantity' => 4])->assertOk();
        $this->assertSame(4, $item->fresh()->quantity);

        $this->putJson("/api/v1/user/cart/items/{$item->id}", ['quantity' => 1])->assertOk();
        $this->assertSame(1, $item->fresh()->quantity);
    }

    public function test_update_quantity_cannot_exceed_stock(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create(['stock' => 3]);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id])->assertOk();

        $item = CartItem::first();

        $this->putJson("/api/v1/user/cart/items/{$item->id}", ['quantity' => 9])->assertStatus(422);
    }

    public function test_update_to_zero_removes_item(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create();
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id])->assertOk();

        $item = CartItem::first();
        $this->putJson("/api/v1/user/cart/items/{$item->id}", ['quantity' => 0])->assertOk();

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_cart_is_grouped_by_vendor(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $vendorA = VendorProfile::factory()->create(['store_name' => 'Vendor A']);
        $vendorB = VendorProfile::factory()->create(['store_name' => 'Vendor B']);

        $a1 = Product::factory()->create(['vendor_id' => $vendorA->id, 'price' => 100, 'gst' => 0, 'stock' => 20]);
        $a2 = Product::factory()->create(['vendor_id' => $vendorA->id, 'price' => 50, 'gst' => 0, 'stock' => 20]);
        $b1 = Product::factory()->create(['vendor_id' => $vendorB->id, 'price' => 200, 'gst' => 0, 'stock' => 20]);

        foreach ([[$a1->id, 2], [$a2->id, 1], [$b1->id, 1]] as [$pid, $qty]) {
            $this->postJson('/api/v1/user/cart/items', ['product_id' => $pid, 'quantity' => $qty])->assertOk();
        }

        $response = $this->getJson('/api/v1/user/cart');

        $response->assertOk()
            ->assertJsonPath('data.vendor_groups.0.vendor.store_name', 'Vendor A')
            ->assertJsonCount(2, 'data.vendor_groups.0.items')
            ->assertJsonPath('data.vendor_groups.0.item_count', 3)
            ->assertJsonPath('data.vendor_groups.0.subtotal', 250)
            ->assertJsonPath('data.vendor_groups.1.vendor.store_name', 'Vendor B')
            ->assertJsonCount(1, 'data.vendor_groups.1.items')
            ->assertJsonPath('data.item_count', 4)
            ->assertJsonPath('data.subtotal', 450);
    }

    public function test_hidden_product_is_dropped_and_reported_on_load(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create();

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id])->assertOk();

        $product->update(['is_active' => false]);

        $response = $this->getJson('/api/v1/user/cart');

        $response->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.messages.0', '"'.$product->name.'" is no longer available and was removed from your cart.');

        $this->assertSame(0, CartItem::count());
    }

    public function test_quantity_is_clamped_when_stock_shrinks_on_load(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create(['stock' => 5]);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 5])->assertOk();

        $product->update(['stock' => 2]);

        $this->getJson('/api/v1/user/cart')->assertJsonPath('data.items.0.quantity', 2);
        $this->assertSame(2, CartItem::first()->fresh()->quantity);
    }

    public function test_price_is_recalculated_from_catalogue_on_load(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create(['price' => 100, 'mrp' => 150, 'stock' => 10]);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id])->assertOk();

        $product->update(['price' => 120]);

        $response = $this->getJson('/api/v1/user/cart');

        $response->assertOk()
            ->assertJsonPath('data.items.0.unit_price', 120)
            ->assertJsonPath('data.items.0.subtotal', 120);
    }

    public function test_remove_and_clear(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create();
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id])->assertOk();

        $item = CartItem::first();
        $this->deleteJson("/api/v1/user/cart/items/{$item->id}")->assertOk();
        $this->assertSame(0, CartItem::count());

        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id])->assertOk();
        $this->deleteJson('/api/v1/user/cart')->assertOk();
        $this->assertSame(0, CartItem::count());
    }

    public function test_totals_include_discount_and_tax(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Product::factory()->create(['price' => 800, 'mrp' => 1000, 'gst' => 18, 'stock' => 10]);

        $this->postJson('/api/v1/user/cart/items', ['product_id' => Product::first()->id, 'quantity' => 1])
            ->assertOk();

        $response = $this->getJson('/api/v1/user/cart/totals');

        $response->assertOk()
            ->assertJsonPath('data.subtotal', 800)
            ->assertJsonPath('data.discount', 200)
            ->assertJsonPath('data.tax', 122.03)
            ->assertJsonPath('data.shipping', 50)
            ->assertJsonPath('data.total', 850);
    }

    public function test_wishlist_item_can_be_moved_to_cart(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create(['gst' => 5, 'stock' => 10]);
        WishlistItem::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->postJson("/api/v1/user/wishlist/{$product->id}/move-to-cart", ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.item_count', 2);

        $this->assertSame(0, WishlistItem::where('user_id', $user->id)->count());
        $this->assertSame(1, CartItem::count());
    }

    public function test_wishlist_move_to_cart_respects_variant(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create(['stock' => 0]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 250, 'stock' => 4]);
        WishlistItem::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->postJson("/api/v1/user/wishlist/{$product->id}/move-to-cart", ['variant_id' => $variant->id])
            ->assertOk();

        $item = CartItem::first();
        $this->assertNotNull($item);
        $this->assertSame($variant->id, $item->product_variant_id);
        $this->assertSame(250.0, (float) $item->unit_price);
    }
}
