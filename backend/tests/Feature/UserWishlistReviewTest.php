<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserWishlistReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_wishlist(): void
    {
        $this->getJson('/api/v1/user/wishlist')->assertUnauthorized();
    }

    public function test_user_can_add_and_remove_wishlist_items(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create();

        $this->postJson('/api/v1/user/wishlist', ['product_id' => $product->id])->assertOk();

        $this->getJson('/api/v1/user/wishlist')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.product.id', $product->id);

        $this->deleteJson("/api/v1/user/wishlist/{$product->id}")->assertOk();

        $this->getJson('/api/v1/user/wishlist')->assertOk()->assertJsonCount(0, 'data.data');
    }

    public function test_adding_wishlist_item_twice_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create();

        $this->postJson('/api/v1/user/wishlist', ['product_id' => $product->id])->assertOk();
        $this->postJson('/api/v1/user/wishlist', ['product_id' => $product->id])->assertOk();

        $this->assertSame(1, WishlistItem::where('user_id', $user->id)->count());
    }

    public function test_user_can_clear_wishlist(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        WishlistItem::factory()->count(3)->create(['user_id' => $user->id]);

        $this->deleteJson('/api/v1/user/wishlist')->assertOk();

        $this->assertSame(0, WishlistItem::where('user_id', $user->id)->count());
    }

    public function test_cannot_add_non_live_product_to_wishlist(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create(['is_active' => false]);

        $this->postJson('/api/v1/user/wishlist', ['product_id' => $product->id])->assertNotFound();
    }

    public function test_review_requires_a_delivered_purchase(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create();

        $this->postJson('/api/v1/user/reviews', [
            'product_id' => $product->id,
            'rating' => 5,
            'title' => 'Great',
            'body' => 'Loved it',
        ])->assertForbidden();

        $order = Order::factory()->create(['user_id' => $user->id, 'order_status' => OrderStatus::Delivered->value]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        $this->postJson('/api/v1/user/reviews', [
            'product_id' => $product->id,
            'rating' => 5,
            'title' => 'Great',
            'body' => 'Loved it',
        ])->assertOk();
    }

    public function test_user_can_view_and_delete_own_reviews(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create();
        $review = Review::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->getJson('/api/v1/user/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $review->id);

        $this->deleteJson("/api/v1/user/reviews/{$review->id}")->assertOk();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_user_cannot_delete_another_users_review(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $review = Review::factory()->create(['user_id' => $other->id]);

        $this->deleteJson("/api/v1/user/reviews/{$review->id}")->assertForbidden();
    }

    public function test_review_requires_pending_product_404_when_not_live(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $order = Order::factory()->create(['user_id' => $user->id, 'order_status' => OrderStatus::Delivered->value]);
        $product = Product::factory()->create(['is_active' => false]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        $this->postJson('/api/v1/user/reviews', [
            'product_id' => $product->id,
            'rating' => 5,
        ])->assertNotFound();
    }
}
