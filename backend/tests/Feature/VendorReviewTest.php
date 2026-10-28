<?php

namespace Tests\Feature;

use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorOrder;
use App\Models\VendorProfile;
use App\Models\VendorReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function deliveredCustomer(): array
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $user = User::factory()->create();

        $order = Order::factory()->create(['user_id' => $user->id]);
        VendorOrder::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Delivered->value,
        ]);

        Sanctum::actingAs($user);

        return [$user, $vendor, $order];
    }

    public function test_verified_vendor_review_is_stored(): void
    {
        [$user, $vendor] = $this->deliveredCustomer();

        $response = $this->postJson('/api/v1/user/vendor-reviews', [
            'vendor_id' => $vendor->id,
            'rating' => 5,
            'title' => 'Great store',
            'body' => 'Fast delivery and good products.',
        ])->assertOk()->json('data.review');

        $this->assertSame(5, $response['rating']);
        $this->assertTrue($response['is_verified']);
        $this->assertFalse($response['is_approved']);
        $this->assertSame($vendor->id, $response['vendor_id']);
    }

    public function test_user_without_completed_order_cannot_review_vendor(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/user/vendor-reviews', ['vendor_id' => $vendor->id, 'rating' => 5])
            ->assertStatus(403);
    }

    public function test_duplicate_vendor_review_rejected_when_updates_disabled(): void
    {
        config()->set('reviews.allow_updates', false);

        [$user, $vendor] = $this->deliveredCustomer();

        $this->postJson('/api/v1/user/vendor-reviews', ['vendor_id' => $vendor->id, 'rating' => 4])->assertOk();
        $this->postJson('/api/v1/user/vendor-reviews', ['vendor_id' => $vendor->id, 'rating' => 2])->assertStatus(409);

        $this->assertSame(1, VendorReview::where('user_id', $user->id)->count());
    }

    public function test_vendor_review_update_allowed_when_configured(): void
    {
        config()->set('reviews.allow_updates', true);
        config()->set('reviews.auto_approve', true);

        [$user, $vendor] = $this->deliveredCustomer();

        $this->postJson('/api/v1/user/vendor-reviews', ['vendor_id' => $vendor->id, 'rating' => 1])->assertOk();

        $this->postJson('/api/v1/user/vendor-reviews', ['vendor_id' => $vendor->id, 'rating' => 5])
            ->assertOk()
            ->assertJsonPath('data.review.rating', 5);

        $this->assertSame(1, VendorReview::where('user_id', $user->id)->count());
    }

    public function test_storefront_only_returns_approved_visible_vendor_reviews(): void
    {
        config()->set('reviews.auto_approve', true);

        [$user, $vendor] = $this->deliveredCustomer();
        $this->postJson('/api/v1/user/vendor-reviews', ['vendor_id' => $vendor->id, 'rating' => 5])->assertOk();
        VendorReview::factory()->create([
            'vendor_id' => $vendor->id,
            'is_approved' => true,
            'is_hidden' => true,
        ]);

        $this->getJson("/api/v1/vendors/{$vendor->id}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_moderate_vendor_review_full_cycle(): void
    {
        [$user, $vendor] = $this->deliveredCustomer();
        $this->postJson('/api/v1/user/vendor-reviews', ['vendor_id' => $vendor->id, 'rating' => 3])->assertOk();

        $review = VendorReview::firstWhere('vendor_id', $vendor->id);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/v1/admin/vendor-reviews?status=pending')->assertOk()->assertJsonCount(1, 'data.data');

        $this->putJson("/api/v1/admin/vendor-reviews/{$review->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.is_approved', true);

        $this->postJson("/api/v1/admin/vendor-reviews/{$review->id}/report", ['reason' => 'Abusive'])
            ->assertOk()
            ->assertJsonPath('data.report_reason', 'Abusive');

        $this->getJson('/api/v1/admin/vendor-reviews?status=reported')->assertOk()->assertJsonCount(1, 'data.data');

        $this->putJson("/api/v1/admin/vendor-reviews/{$review->id}/hide")
            ->assertOk()
            ->assertJsonPath('data.is_hidden', true);

        $this->deleteJson("/api/v1/admin/vendor-reviews/{$review->id}")->assertOk();

        $this->assertDatabaseMissing('vendor_reviews', ['id' => $review->id]);
    }

    public function test_customer_can_delete_only_own_vendor_review(): void
    {
        [$user, $vendor] = $this->deliveredCustomer();
        $other = User::factory()->create();
        $review = VendorReview::factory()->create(['user_id' => $other->id, 'vendor_id' => $vendor->id]);

        $this->deleteJson("/api/v1/user/vendor-reviews/{$review->id}")->assertStatus(403);
    }
}
