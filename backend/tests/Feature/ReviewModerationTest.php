<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function deliveredReviewer(): array
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);
        $user = User::factory()->create();

        $order = Order::factory()->create(['user_id' => $user->id, 'order_status' => OrderStatus::Delivered->value]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'vendor_id' => $vendor->id]);

        Sanctum::actingAs($user);

        return [$user, $product, $order, $vendor];
    }

    public function test_verified_purchase_badge_and_images_are_stored(): void
    {
        [$user, $product] = $this->deliveredReviewer();

        $response = $this->postJson('/api/v1/user/reviews', [
            'product_id' => $product->id,
            'rating' => 4,
            'title' => 'Nice product',
            'body' => 'Works well.',
            'images' => ['https://cdn.test/a.jpg', 'https://cdn.test/b.jpg'],
        ])->assertOk()->json('data.review');

        $this->assertSame(4, $response['rating']);
        $this->assertTrue($response['is_verified']);
        $this->assertSame(['https://cdn.test/a.jpg', 'https://cdn.test/b.jpg'], $response['images']);
        $this->assertFalse($response['is_approved']);
    }

    public function test_duplicate_review_rejected_when_updates_disabled(): void
    {
        config()->set('reviews.allow_updates', false);
        config()->set('reviews.auto_approve', false);

        [$user, $product] = $this->deliveredReviewer();

        $this->postJson('/api/v1/user/reviews', ['product_id' => $product->id, 'rating' => 5])->assertOk();
        $this->postJson('/api/v1/user/reviews', ['product_id' => $product->id, 'rating' => 3])->assertStatus(409);

        $this->assertSame(1, Review::where('user_id', $user->id)->count());
    }

    public function test_review_update_allowed_when_configured(): void
    {
        config()->set('reviews.allow_updates', true);
        config()->set('reviews.auto_approve', true);

        [$user, $product] = $this->deliveredReviewer();

        $this->postJson('/api/v1/user/reviews', ['product_id' => $product->id, 'rating' => 2])->assertOk();

        $review = Review::where('user_id', $user->id)->firstOrFail();

        $this->putJson("/api/v1/user/reviews/{$review->id}", ['rating' => 5, 'title' => 'Changed after use'])
            ->assertOk()
            ->assertJsonPath('data.review.rating', 5);

        $this->assertSame(1, Review::where('user_id', $user->id)->count());
    }

    public function test_auto_approve_config_controls_immediate_visibility(): void
    {
        config()->set('reviews.auto_approve', true);

        [$user, $product] = $this->deliveredReviewer();

        $this->postJson('/api/v1/user/reviews', ['product_id' => $product->id, 'rating' => 5])
            ->assertOk()
            ->assertJsonPath('data.review.is_approved', true);

        $this->getJson("/api/v1/products/{$product->id}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_hidden_review_is_not_returned_on_storefront(): void
    {
        [$user, $product] = $this->deliveredReviewer();
        $this->postJson('/api/v1/user/reviews', ['product_id' => $product->id, 'rating' => 1])->assertOk();

        $review = Review::firstWhere('product_id', $product->id);
        $review->forceFill(['is_approved' => true, 'is_hidden' => true])->save();

        $this->getJson("/api/v1/products/{$product->id}/reviews")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_can_moderate_review_full_cycle(): void
    {
        [$user, $product] = $this->deliveredReviewer();
        $this->postJson('/api/v1/user/reviews', ['product_id' => $product->id, 'rating' => 3])->assertOk();

        $review = Review::firstWhere('product_id', $product->id);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/v1/admin/reviews?status=pending')->assertOk()->assertJsonCount(1, 'data.data');

        $this->putJson("/api/v1/admin/reviews/{$review->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.is_approved', true);

        $this->postJson("/api/v1/admin/reviews/{$review->id}/report", ['reason' => 'Spam'])
            ->assertOk()
            ->assertJsonPath('data.report_reason', 'Spam');

        $this->getJson('/api/v1/admin/reviews?status=reported')->assertOk()->assertJsonCount(1, 'data.data');

        $this->putJson("/api/v1/admin/reviews/{$review->id}/hide")
            ->assertOk()
            ->assertJsonPath('data.is_hidden', true);

        $this->putJson("/api/v1/admin/reviews/{$review->id}/images", ['images' => ['https://cdn.test/ok.jpg']])
            ->assertOk()
            ->assertJsonPath('data.images.0', 'https://cdn.test/ok.jpg');

        $this->deleteJson("/api/v1/admin/reviews/{$review->id}")->assertOk();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_customer_cannot_update_another_users_review(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/user/reviews/{$review->id}", ['rating' => 1])->assertStatus(403);
    }
}
