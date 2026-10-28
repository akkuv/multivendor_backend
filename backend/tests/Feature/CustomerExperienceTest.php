<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_view_and_recently_viewed(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/user/products/{$product->id}/view")->assertOk();

        $this->getJson('/api/v1/user/recently-viewed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id);

        $this->postJson("/api/v1/user/products/{$product->id}/view")->assertOk();
        $this->assertDatabaseHas('recently_viewed', ['user_id' => $user->id, 'product_id' => $product->id, 'view_count' => 2]);
    }

    public function test_recently_viewed_excludes_other_users_history(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/user/products/{$product->id}/view")->assertOk();

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/user/products/{$otherProduct->id}/view")->assertOk();

        $this->getJson('/api/v1/user/recently-viewed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $otherProduct->id);
    }

    public function test_buy_again_returns_products_from_delivered_orders(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $order = Order::factory()->create(['user_id' => $user->id, 'order_status' => OrderStatus::Delivered->value]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/buy-again')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id);
    }

    public function test_related_products_share_category(): void
    {
        $product = Product::factory()->create();
        $related = Product::factory()->create(['category_id' => $product->category_id]);
        Product::factory()->create();

        $this->getJson("/api/v1/products/{$product->id}/related")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $related->id);
    }

    public function test_frequently_bought_together_uses_co_occurrence(): void
    {
        $product = Product::factory()->create();
        $co = Product::factory()->create();

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'order_status' => OrderStatus::Delivered->value]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $co->id]);

        $this->getJson("/api/v1/products/{$product->id}/frequently-bought")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $co->id);
    }

    public function test_recommendations_fall_back_to_products_without_history(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/recommendations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id);
    }

    public function test_compare_returns_products_in_requested_order(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();

        $this->getJson("/api/v1/products/compare?ids={$b->id},{$a->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.products')
            ->assertJsonPath('data.order', [$b->id, $a->id]);
    }

    public function test_compare_requires_at_least_one_product(): void
    {
        $this->getJson('/api/v1/products/compare')->assertStatus(422);
    }
}
