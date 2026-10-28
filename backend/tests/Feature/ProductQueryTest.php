<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\SupportConversationKind;
use App\Enums\SupportSenderRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SupportConversation;
use App\Models\User;
use App\Models\VendorProfile;
use App\Notifications\AppNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductQueryTest extends TestCase
{
    use RefreshDatabase;

    /* ----------------------------------------------------------------- */
    /* Customer side: creating a query */
    /* ----------------------------------------------------------------- */

    public function test_customer_can_create_a_product_query_and_vendor_is_notified(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/user/product-queries', [
            'product_id' => $product->id,
            'body' => 'Is this available in a bigger size?',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.conversation.kind', SupportConversationKind::ProductQuery->value)
            ->assertJsonPath('data.conversation.product.name', $product->name)
            ->assertJsonPath('data.conversation.product.vendor.store_name', $vendor->store_name)
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Customer->value);

        $this->assertDatabaseHas('support_conversations', [
            'kind' => SupportConversationKind::ProductQuery->value,
            'product_id' => $product->id,
            'vendor_id' => $vendor->id,
            'customer_user_id' => $customer->id,
            'requester_type' => 'CUSTOMER',
        ]);

        $this->assertDatabaseHas('support_messages', [
            'sender_role' => SupportSenderRole::Customer->value,
            'body' => 'Is this available in a bigger size?',
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $vendor->user->id,
            'data->type' => NotificationType::ProductQueryForVendor->value,
        ]);

        // The admin team is told straight away too — the query is theirs to
        // answer alongside the vendor.
        $this->assertDatabaseHas('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $admin->id,
            'data->type' => NotificationType::ProductQueryForAdmin->value,
        ]);

        // The customer's own message does not count as unread for them, but the
        // thread sits with the admin from the moment it is created.
        $this->assertSame(0, $this->conversations($product, $customer)->first()->messages()
            ->unreadFor(SupportSenderRole::Customer->value)->count());
        $this->assertSame(
            1,
            $this->conversations($product, $customer)->first()->messages()
                ->unreadFor(SupportSenderRole::Admin->value)->count(),
        );
    }

    public function test_customer_can_create_a_product_query_with_an_image(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $imageUrl = 'https://example.com/uploads/images/2026-10/question.png';

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/user/product-queries', [
            'product_id' => $product->id,
            'body' => 'Is this the shade in the third photo?',
            'image_url' => $imageUrl,
        ])->assertStatus(201)
            ->assertJsonPath('data.message.image_url', $imageUrl);

        $this->assertDatabaseHas('support_messages', [
            'sender_role' => SupportSenderRole::Customer->value,
            'image_url' => $imageUrl,
        ]);
    }

    public function test_product_query_rejects_an_invalid_image_url(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/user/product-queries', [
            'product_id' => $product->id,
            'body' => 'Is it in stock?',
            'image_url' => 'not-a-url',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('image_url');
    }

    public function test_creating_again_for_the_same_product_starts_a_new_thread(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/user/product-queries', [
            'product_id' => $product->id,
            'body' => 'First question',
        ])->assertStatus(201);

        $this->postJson('/api/v1/user/product-queries', [
            'product_id' => $product->id,
            'body' => 'A follow-up about the same product',
        ])->assertStatus(201);

        // Unlike order queries there is no find-or-create: every enquiry is its
        // own private thread so the vendor can turn one around without the
        // other being buried.
        $this->assertSame(2, SupportConversation::query()->where('product_id', $product->id)->count());
        $this->assertSame(1, $this->conversations($product, $customer)->first()->messages()->count());
    }

    public function test_vendor_cannot_create_a_product_query(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();

        Sanctum::actingAs($vendor->user);

        $this->postJson('/api/v1/user/product-queries', [
            'product_id' => $product->id,
            'body' => 'A vendor asking on the customer endpoint.',
        ])->assertForbidden();
    }

    /* ----------------------------------------------------------------- */
    /* Admin side: inbox and replies */
    /* ----------------------------------------------------------------- */

    public function test_admin_product_query_inbox_stays_separate_from_customer_support(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $this->makeProductQuery($product, $customer, 'How heavy is this?');

        $general = SupportConversation::query()->create([
            'kind' => SupportConversationKind::General->value,
            'requester_type' => 'CUSTOMER',
            'customer_user_id' => $customer->id,
            'subject' => 'General question',
        ]);
        $general->messages()->create([
            'sender_user_id' => $customer->id,
            'sender_role' => SupportSenderRole::Customer->value,
            'body' => 'Where is my order?',
        ]);
        $general->forceFill([
            'last_message_at' => now(),
            'last_requester_reply_at' => now(),
        ])->save();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/customer-support')
            ->assertOk()
            ->assertJsonPath('data.counts.awaiting_admin', 1)
            ->assertJsonCount(1, 'data.data');

        $this->getJson('/api/v1/admin/product-queries')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.kind', SupportConversationKind::ProductQuery->value)
            ->assertJsonPath('data.data.0.product.name', $product->name)
            ->assertJsonPath('data.counts.awaiting_admin', 1);
    }

    public function test_admin_can_reply_to_a_product_query(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $conversation = $this->makeProductQuery($product, $customer, 'Do you ship internationally?');

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/product-queries/{$conversation->id}/messages", [
            'body' => 'We ship worldwide.',
        ])->assertOk()
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Admin->value)
            ->assertJsonPath('data.message.is_bot', false);
    }

    public function test_admin_can_reply_to_a_product_query_with_an_image(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $conversation = $this->makeProductQuery($product, $customer, 'Do you ship internationally?');
        $imageUrl = 'https://example.com/uploads/images/2026-10/photo.png';

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/product-queries/{$conversation->id}/messages", [
            'body' => 'We ship worldwide.',
            'image_url' => $imageUrl,
        ])->assertOk()
            ->assertJsonPath('data.message.image_url', $imageUrl)
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Admin->value);
    }

    /* ----------------------------------------------------------------- */
    /* Vendor side */
    /* ----------------------------------------------------------------- */

    public function test_vendor_can_list_own_product_queries_with_product_context(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $this->makeProductQuery($product, $customer, 'What is the return policy?');

        Sanctum::actingAs($vendor->user);

        $this->getJson('/api/v1/vendor/product-queries')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.product.name', $product->name)
            ->assertJsonPath('data.data.0.unread_for_vendor', 1);
    }

    public function test_vendor_can_open_and_reply_to_a_product_query(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $conversation = $this->makeProductQuery($product, $customer, 'Is it in stock?');

        Sanctum::actingAs($vendor->user);

        $this->getJson("/api/v1/vendor/product-queries/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.conversation.customer.name', $customer->name)
            ->assertJsonPath('data.conversation.product.name', $product->name);

        $this->postJson("/api/v1/vendor/product-queries/{$conversation->id}/messages", [
            'body' => 'Yes, 40 units in stock.',
        ])->assertOk()
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Vendor->value)
            ->assertJsonPath('data.message.is_bot', false)
            ->assertJsonMissingPath('data.bot_reply');
    }

    public function test_vendor_can_reply_to_a_product_query_with_an_image(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $conversation = $this->makeProductQuery($product, $customer, 'Is it in stock?');
        $imageUrl = 'https://example.com/uploads/images/2026-10/stock.png';

        Sanctum::actingAs($vendor->user);

        $this->postJson("/api/v1/vendor/product-queries/{$conversation->id}/messages", [
            'body' => 'Yes, 40 units in stock.',
            'image_url' => $imageUrl,
        ])->assertOk()
            ->assertJsonPath('data.message.image_url', $imageUrl)
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Vendor->value);
    }

    public function test_vendor_reply_to_a_customer_product_query_notifies_the_customer(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $conversation = $this->makeProductQuery($product, $customer, 'Is it in stock?');

        Sanctum::actingAs($vendor->user);

        $this->postJson("/api/v1/vendor/product-queries/{$conversation->id}/messages", [
            'body' => 'Yes, 40 units in stock.',
        ])->assertOk()
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Vendor->value);

        $this->assertDatabaseMissing('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $customer->id,
            'data->type' => NotificationType::ProductQueryReplyForCustomer->value,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $admin->id,
            'data->type' => NotificationType::ProductQueryForAdmin->value,
        ]);
    }

    public function test_vendor_cannot_read_another_vendors_product_query(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $conversation = $this->makeProductQuery($product, $customer, 'Is it genuine?');

        $otherVendor = VendorProfile::factory()->approved()->create();
        Sanctum::actingAs($otherVendor->user);

        $this->getJson("/api/v1/vendor/product-queries/{$conversation->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('conversation');

        $this->postJson("/api/v1/vendor/product-queries/{$conversation->id}/messages", [
            'body' => 'Sneaky reply.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('conversation');
    }

    public function test_vendor_product_query_unread_count_excludes_general_support(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $conversation = $this->makeProductQuery($product, $customer, 'Colour options?');

        $general = SupportConversation::query()->create([
            'requester_type' => 'VENDOR',
            'vendor_id' => $vendor->id,
            'vendor_user_id' => $vendor->user_id,
            'subject' => 'General support',
        ]);
        $general->messages()->create([
            'sender_user_id' => $admin->id,
            'sender_role' => SupportSenderRole::Admin->value,
            'body' => 'General support message.',
        ]);

        Sanctum::actingAs($vendor->user);

        $this->getJson('/api/v1/vendor/product-queries/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->getJson('/api/v1/vendor/support/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $conversation->messages()->update(['read_at' => now()]);

        $this->getJson('/api/v1/vendor/product-queries/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    /* ----------------------------------------------------------------- */
    /* Reading and waiting-on semantics */
    /* ----------------------------------------------------------------- */

    public function test_product_query_thread_exposes_product_price_and_purchase_history(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $conversation = $this->makeProductQuery($product, $customer, 'How soon can it ship?');

        $order = Order::factory()->create(['user_id' => $customer->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
        ]);

        $assert = function (string $url) use ($order, $product): void {
            $conversation = $this->getJson($url)->assertOk()->json('data.conversation');

            $this->assertEqualsWithDelta((float) $product->price, $conversation['product']['price'], 0.001);
            $this->assertEqualsWithDelta((float) $product->mrp, $conversation['product']['mrp'], 0.001);
            $this->assertSame($order->order_number, $conversation['product_purchases'][0]['order_number']);
            $this->assertSame(OrderStatus::Pending->value, $conversation['product_purchases'][0]['order_status']);
            $this->assertSame(2, $conversation['product_purchases'][0]['quantity']);
        };

        // The customer who asked, the selling vendor and the admin team can all
        // open the product query alongside the product price and the asking
        // customer's purchase history for that product.
        Sanctum::actingAs($customer);
        $assert("/api/v1/user/product-queries/{$conversation->id}");

        Sanctum::actingAs($vendor->user);
        $assert("/api/v1/vendor/product-queries/{$conversation->id}");

        Sanctum::actingAs($admin);
        $assert("/api/v1/admin/product-queries/{$conversation->id}");
    }

    public function test_customer_product_query_unread_count_clears_when_the_thread_is_read(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $conversation = $this->makeProductQuery($product, $customer, 'Do you ship internationally?');
        $conversation->messages()->create([
            'sender_user_id' => $admin->id,
            'sender_role' => SupportSenderRole::Admin->value,
            'body' => 'We ship worldwide.',
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/user/product-queries/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->postJson("/api/v1/user/product-queries/{$conversation->id}/read")
            ->assertOk();

        $this->getJson('/api/v1/user/product-queries/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_vendor_reply_sits_with_the_admin_until_an_admin_writes(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $conversation = $this->makeProductQuery($product, $customer, 'Is it in stock?');

        Sanctum::actingAs($vendor->user);
        $this->postJson("/api/v1/vendor/product-queries/{$conversation->id}/messages", [
            'body' => 'Yes, in stock.',
        ])->assertOk();

        // The customer and the vendor share the requester side of a product
        // query, so a vendor reply leaves the thread waiting on the admin team.
        $this->assertTrue($conversation->fresh()->isWaitingOnAdmin());

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/product-queries')
            ->assertOk()
            ->assertJsonPath('data.counts.awaiting_admin', 1)
            ->assertJsonPath('data.data.0.is_waiting_on_admin', true);
    }

    public function test_customer_can_reply_to_their_product_query_without_the_bot(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $conversation = $this->makeProductQuery($product, $customer, 'What colours are available?');

        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/user/product-queries/{$conversation->id}/messages", [
            'body' => 'Actually, is it also in blue?',
        ])->assertOk()
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Customer->value)
            ->assertJsonPath('data.message.is_bot', false)
            ->assertJsonMissingPath('data.bot_reply');
    }

    public function test_customer_can_reply_to_a_product_query_with_an_image(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();
        $conversation = $this->makeProductQuery($product, $customer, 'What colours are available?');
        $imageUrl = 'https://example.com/uploads/images/2026-10/colour.png';

        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/user/product-queries/{$conversation->id}/messages", [
            'body' => 'Is this the same blue as the photo?',
            'image_url' => $imageUrl,
        ])->assertOk()
            ->assertJsonPath('data.message.image_url', $imageUrl)
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Customer->value)
            ->assertJsonMissingPath('data.bot_reply');
    }

    /* ----------------------------------------------------------------- */
    /* Vendor and admin opening a query */
    /* ----------------------------------------------------------------- */

    public function test_vendor_can_create_a_product_query_about_own_product_and_admins_are_notified(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();

        Sanctum::actingAs($vendor->user);

        $response = $this->postJson('/api/v1/vendor/product-queries', [
            'product_id' => $product->id,
            'body' => 'Can you review my listing photos?',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.conversation.kind', SupportConversationKind::ProductQuery->value)
            ->assertJsonPath('data.conversation.requester_type', 'VENDOR')
            ->assertJsonPath('data.conversation.customer_user_id', null)
            ->assertJsonPath('data.conversation.product.name', $product->name)
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Vendor->value);

        $this->assertDatabaseHas('support_conversations', [
            'kind' => SupportConversationKind::ProductQuery->value,
            'product_id' => $product->id,
            'vendor_id' => $vendor->id,
            'vendor_user_id' => $vendor->user_id,
            'customer_user_id' => null,
            'requester_type' => 'VENDOR',
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $admin->id,
            'data->type' => NotificationType::ProductQueryForAdmin->value,
        ]);
    }

    public function test_vendor_cannot_create_a_product_query_about_another_vendors_product(): void
    {
        [, $vendor] = $this->productQueryContext();
        $otherVendor = VendorProfile::factory()->approved()->create();
        $otherProduct = Product::factory()->create(['vendor_id' => $otherVendor->id]);

        Sanctum::actingAs($vendor->user);

        $this->postJson('/api/v1/vendor/product-queries', [
            'product_id' => $otherProduct->id,
            'body' => 'A question about another store.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('product_id');

        $this->assertDatabaseMissing('support_conversations', [
            'kind' => SupportConversationKind::ProductQuery->value,
            'product_id' => $otherProduct->id,
        ]);
    }

    public function test_vendor_created_product_query_appears_in_own_list_and_admin_inbox(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();

        Sanctum::actingAs($vendor->user);

        $this->postJson('/api/v1/vendor/product-queries', [
            'product_id' => $product->id,
            'body' => 'Please review my listing.',
        ])->assertStatus(201);

        // The vendor's own list shows the thread they opened, not just the
        // queries customers raised against their products.
        $this->getJson('/api/v1/vendor/product-queries')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.requester_type', 'VENDOR')
            ->assertJsonPath('data.data.0.product.name', $product->name);

        // The admin product-query inbox serves customer- and vendor-requester
        // threads alike, and the vendor's opening message leaves it waiting.
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/product-queries')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.requester_type', 'VENDOR')
            ->assertJsonPath('data.counts.awaiting_admin', 1)
            ->assertJsonPath('data.counts.unread_messages', 1);
    }

    public function test_admin_can_create_a_product_query_and_vendor_is_notified(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/product-queries', [
            'product_id' => $product->id,
            'body' => 'Please confirm this listing is still in stock.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.conversation.kind', SupportConversationKind::ProductQuery->value)
            ->assertJsonPath('data.conversation.requester_type', 'VENDOR')
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Admin->value);

        $this->assertDatabaseHas('support_conversations', [
            'kind' => SupportConversationKind::ProductQuery->value,
            'product_id' => $product->id,
            'vendor_id' => $vendor->id,
            'vendor_user_id' => $vendor->user_id,
            'requester_type' => 'VENDOR',
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $vendor->user->id,
            'data->type' => NotificationType::ProductQueryForVendor->value,
        ]);
    }

    public function test_admin_created_product_query_is_visible_to_the_vendor_and_answerable(): void
    {
        [$admin, $vendor, $product, $customer] = $this->productQueryContext();

        Sanctum::actingAs($admin);

        $conversationId = $this->postJson('/api/v1/admin/product-queries', [
            'product_id' => $product->id,
            'body' => 'Please update the listing photos.',
        ])->assertStatus(201)->json('data.conversation.id');

        // The vendor sees it in their own product-query list and can answer,
        // exactly like a customer-initiated thread.
        Sanctum::actingAs($vendor->user);

        $this->getJson('/api/v1/vendor/product-queries')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.requester_type', 'VENDOR');

        $this->postJson("/api/v1/vendor/product-queries/{$conversationId}/messages", [
            'body' => 'Done — new photos uploaded.',
        ])->assertOk()
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Vendor->value);

        // The admin's opening message stays unread for the vendor until they
        // open the thread, so the sidebar badge still shows it.
        $this->getJson('/api/v1/vendor/product-queries/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);
    }

    /* ----------------------------------------------------------------- */
    /* Helpers */
    /* ----------------------------------------------------------------- */

    /**
     * An admin, an approved vendor, a product that vendor sells and the
     * customer who asks about it.
     *
     * @return array{0: User, 1: VendorProfile, 2: Product, 3: User}
     */
    private function productQueryContext(): array
    {
        $admin = User::factory()->superAdmin()->create();
        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);
        $customer = User::factory()->customer()->create();

        return [$admin, $vendor, $product, $customer];
    }

    private function makeProductQuery(Product $product, User $customer, string $body): SupportConversation
    {
        $conversation = SupportConversation::query()->create([
            'kind' => SupportConversationKind::ProductQuery->value,
            'requester_type' => 'CUSTOMER',
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'vendor_user_id' => $product->vendor?->user_id,
            'customer_user_id' => $customer->id,
            'subject' => "Product enquiry: {$product->name}",
        ]);

        $conversation->messages()->create([
            'sender_user_id' => $customer->id,
            'sender_role' => SupportSenderRole::Customer->value,
            'body' => $body,
        ]);

        // The real create flow records the message through the service, which
        // bumps the activity timestamps; the helper must do the same so inbox
        // "waiting on you" and preview ordering behave identically.
        $conversation->forceFill([
            'last_message_at' => now(),
            'last_requester_reply_at' => now(),
        ])->save();

        return $conversation;
    }

    private function conversations(Product $product, User $customer): Builder
    {
        return SupportConversation::query()
            ->where('product_id', $product->id)
            ->where('customer_user_id', $customer->id);
    }
}
