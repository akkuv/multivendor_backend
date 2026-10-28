<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WishlistNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_to_wishlist_captures_price_at_add(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 500]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/wishlist', ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonPath('data.product.id', $product->id);

        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'price_at_add' => 500,
        ]);
    }

    public function test_user_can_toggle_notification_preferences(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/wishlist', ['product_id' => $product->id])->assertOk();

        $this->putJson("/api/v1/user/wishlist/{$product->id}/notifications", [
            'notify_price_drops' => true,
            'notify_back_in_stock' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.notify_price_drops', true)
            ->assertJsonPath('data.notify_back_in_stock', true);

        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'notify_price_drops' => 1,
            'notify_back_in_stock' => 1,
        ]);
    }

    public function test_command_creates_price_drop_notification(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 80]);

        $user->wishlistItems()->create([
            'product_id' => $product->id,
            'price_at_add' => 100,
            'notify_price_drops' => true,
        ]);

        $this->artisan('wishlist:check-notifications')->assertSuccessful();

        $this->assertDatabaseHas('wishlist_notifications', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'type' => 'price_drop',
            'old_price' => 100,
            'new_price' => 80,
        ]);
    }

    public function test_command_creates_back_in_stock_notification(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 50, 'stock' => 5]);

        $user->wishlistItems()->create([
            'product_id' => $product->id,
            'price_at_add' => 50,
            'notify_back_in_stock' => true,
        ]);

        $this->artisan('wishlist:check-notifications')->assertSuccessful();

        $this->assertDatabaseHas('wishlist_notifications', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'type' => 'back_in_stock',
        ]);
    }

    public function test_command_does_not_duplicate_notifications(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 80]);

        $user->wishlistItems()->create([
            'product_id' => $product->id,
            'price_at_add' => 100,
            'notify_price_drops' => true,
        ]);

        $this->artisan('wishlist:check-notifications')->assertSuccessful();
        $this->artisan('wishlist:check-notifications')->assertSuccessful();

        $this->assertEquals(1, WishlistNotification::where('user_id', $user->id)->count());
    }

    public function test_notifications_inbox_and_mark_read(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 70]);

        $item = $user->wishlistItems()->create([
            'product_id' => $product->id,
            'price_at_add' => 90,
            'notify_price_drops' => true,
        ]);

        $this->artisan('wishlist:check-notifications')->assertSuccessful();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/wishlist-notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.type', 'price_drop');

        $this->assertFalse((bool) WishlistNotification::where('user_id', $user->id)->firstOrFail()->is_read);

        $this->postJson('/api/v1/user/wishlist-notifications/read')->assertOk();

        $this->assertTrue((bool) WishlistNotification::where('user_id', $user->id)->firstOrFail()->is_read);
    }
}
