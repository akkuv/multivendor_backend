<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSlugRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function liveProduct(): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'status' => 'PUBLISHED',
            'approval_status' => 'APPROVED',
        ]);
    }

    public function test_storefront_product_can_be_fetched_by_slug(): void
    {
        $product = $this->liveProduct();

        $this->assertNotEmpty($product->slug);

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_storefront_product_can_still_be_fetched_by_id(): void
    {
        $product = $this->liveProduct();

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.slug', $product->slug);
    }

    public function test_nested_product_routes_accept_a_slug(): void
    {
        $product = $this->liveProduct();

        $this->getJson("/api/v1/products/{$product->slug}/related")
            ->assertOk();

        $this->getJson("/api/v1/products/{$product->slug}/offers")
            ->assertOk();
    }
}
