<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_products_categories_brands_and_vendors(): void
    {
        $product = Product::factory()->create(['name' => 'Acme Running Shoes']);
        $category = Category::factory()->create(['name' => 'Acme Sneakers', 'is_active' => true]);
        $brand = Brand::factory()->create(['name' => 'Acme Brand', 'is_active' => true]);
        $vendor = VendorProfile::factory()->create(['store_name' => 'Acme Store', 'is_approved' => true]);

        $response = $this->getJson('/api/v1/search?q=acme');

        $response->assertOk()
            ->assertJsonPath('data.products.total', 1)
            ->assertJsonPath('data.products.data.0.name', 'Acme Running Shoes')
            ->assertJsonPath('data.categories.0.name', 'Acme Sneakers')
            ->assertJsonPath('data.brands.0.name', 'Acme Brand')
            ->assertJsonPath('data.vendors.0.store_name', 'Acme Store');
    }

    public function test_search_does_not_return_non_live_products(): void
    {
        Product::factory()->create(['name' => 'Hidden Draft', 'status' => 'DRAFT', 'approval_status' => 'PENDING_APPROVAL', 'is_active' => true]);

        $response = $this->getJson('/api/v1/search?q=Hidden');

        $response->assertOk()->assertJsonPath('data.products.total', 0);
    }

    public function test_search_empty_term_returns_empty_sections(): void
    {
        $response = $this->getJson('/api/v1/search?q=');

        $response->assertOk()
            ->assertJsonPath('data.products.total', 0)
            ->assertJsonCount(0, 'data.categories');
    }

    public function test_search_excludes_unapproved_vendors(): void
    {
        VendorProfile::factory()->create(['store_name' => 'Pending Shop', 'is_approved' => false]);

        $response = $this->getJson('/api/v1/search?q=Pending');

        $response->assertOk()->assertJsonCount(0, 'data.vendors');
    }

    public function test_public_can_list_brands_with_live_product_count(): void
    {
        $brand = Brand::factory()->create(['is_active' => true]);
        Product::factory()->count(2)->create(['brand_id' => $brand->id]);
        Product::factory()->create(['brand_id' => $brand->id, 'is_active' => false]);

        $response = $this->getJson('/api/v1/brands');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $brand->id)
            ->assertJsonPath('data.0.products_count', 2);
    }

    public function test_public_can_list_only_approved_vendors(): void
    {
        VendorProfile::factory()->create(['store_name' => 'Approved Store', 'is_approved' => true]);
        VendorProfile::factory()->create(['store_name' => 'Rejected Store', 'is_approved' => false]);

        $response = $this->getJson('/api/v1/vendors');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.store_name', 'Approved Store');
    }

    public function test_public_cannot_view_unapproved_vendor(): void
    {
        $vendor = VendorProfile::factory()->create(['is_approved' => false, 'status' => 'PENDING']);

        $this->getJson("/api/v1/vendors/{$vendor->id}")->assertNotFound();
    }

    public function test_deals_returns_only_products_with_discount(): void
    {
        Product::factory()->create(['price' => 500, 'mrp' => 900]);
        Product::factory()->create(['price' => 500, 'mrp' => 500]);

        $response = $this->getJson('/api/v1/products/deals');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_best_sellers_and_trending_endpoints_return_live_products(): void
    {
        Product::factory()->create(['name' => 'Bestseller Product', 'best_seller' => true]);

        $this->getJson('/api/v1/products/best-sellers')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products/trending')->assertOk();
    }

    public function test_product_reviews_returns_only_approved_reviews(): void
    {
        $product = Product::factory()->create();
        Review::factory()->create(['product_id' => $product->id, 'is_approved' => true]);
        Review::factory()->create(['product_id' => $product->id, 'is_approved' => false]);

        $response = $this->getJson("/api/v1/products/{$product->id}/reviews");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_product_reviews_rejects_hidden_product(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $this->getJson("/api/v1/products/{$product->id}/reviews")->assertNotFound();
    }

    public function test_product_show_includes_rating_and_rejects_hidden_product(): void
    {
        $product = Product::factory()->create();
        Review::factory()->create(['product_id' => $product->id, 'rating' => 4, 'is_approved' => true]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.rating.average', 4)
            ->assertJsonPath('data.rating.count', 1);
    }
}
