<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\SupportConversationKind;
use App\Enums\SupportSenderRole;
use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SupportConversation;
use App\Models\User;
use App\Models\VendorOrder;
use App\Models\VendorProfile;
use App\Notifications\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderQueryTest extends TestCase
{
    use RefreshDatabase;

    /* ----------------------------------------------------------------- */
    /* Admin side: creating a query */
    /* ----------------------------------------------------------------- */

    public function test_admin_can_create_an_order_query_to_vendor_and_notify_them(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/order-queries', [
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'subject' => 'Stock confirmation needed',
            'body' => 'Can you confirm how many units remain in stock?',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.conversation.kind', SupportConversationKind::OrderQuery->value)
            ->assertJsonPath('data.conversation.order.order_number', $order->order_number)
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Admin->value);

        $this->assertDatabaseHas('support_conversations', [
            'kind' => SupportConversationKind::OrderQuery->value,
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'requester_type' => 'VENDOR',
        ]);

        $this->assertDatabaseHas('support_messages', [
            'sender_role' => SupportSenderRole::Admin->value,
            'body' => 'Can you confirm how many units remain in stock?',
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $vendor->user->id,
            'data->type' => NotificationType::OrderQueryForVendor->value,
        ]);
    }

    public function test_querying_the_same_order_and_vendor_reuses_the_thread(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();

        Sanctum::actingAs($admin);

        $payload = [
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
        ];

        $this->postJson('/api/v1/admin/order-queries', $payload + [
            'subject' => 'First enquiry',
            'body' => 'First message',
        ])->assertStatus(201);

        $this->postJson('/api/v1/admin/order-queries', $payload + [
            'subject' => 'Follow up',
            'body' => 'Second message',
        ])->assertStatus(201);

        $this->assertSame(1, SupportConversation::query()->where('order_id', $order->id)->count());
        $this->assertSame(2, SupportConversation::where('order_id', $order->id)->first()->messages()->count());
    }

    public function test_admin_cannot_query_a_vendor_not_involved_in_the_order(): void
    {
        [$admin, $order, $vendor] = $this->queryableOrder();
        $otherVendor = VendorProfile::factory()->approved()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/order-queries', [
            'order_id' => $order->id,
            'vendor_id' => $otherVendor->id,
            'subject' => 'Wrong vendor',
            'body' => 'This should fail.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('vendor_id');

        $this->assertDatabaseMissing('support_conversations', [
            'kind' => SupportConversationKind::OrderQuery->value,
            'vendor_id' => $otherVendor->id,
        ]);
    }

    public function test_customer_cannot_create_an_order_query(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();
        $customer = User::factory()->customer()->create();

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/admin/order-queries', [
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'subject' => 'Nope',
            'body' => 'Customers do not raise vendor queries.',
        ])->assertForbidden();
    }

    /* ----------------------------------------------------------------- */
    /* Admin side: inbox and replies */
    /* ----------------------------------------------------------------- */

    public function test_admin_order_query_inbox_stays_separate_from_general_support(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();
        $this->makeOrderQuery($admin, $vendor, $order);

        SupportConversation::query()->create([
            'requester_type' => 'VENDOR',
            'vendor_id' => $vendor->id,
            'vendor_user_id' => $vendor->user_id,
            'subject' => 'General question',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/support')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');

        $this->getJson('/api/v1/admin/order-queries')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.kind', SupportConversationKind::OrderQuery->value)
            ->assertJsonPath('data.data.0.order.order_number', $order->order_number)
            ->assertJsonPath('data.data.0.order.vendor_order_id', $order->vendorOrders()->where('vendor_id', $vendor->id)->value('id'));
    }

    public function test_admin_can_reply_to_an_order_query(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();
        $conversation = $this->makeOrderQuery($admin, $vendor, $order);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/order-queries/{$conversation->id}/messages", [
            'body' => 'Please share the dispatch date.',
        ])->assertOk()
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Admin->value);
    }

    public function test_admin_can_reply_to_an_order_query_with_an_image(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();
        $conversation = $this->makeOrderQuery($admin, $vendor, $order);

        Sanctum::actingAs($admin);

        $imageUrl = 'https://example.com/uploads/images/2026-10/photo.png';

        $this->postJson("/api/v1/admin/order-queries/{$conversation->id}/messages", [
            'body' => 'Please share a photo of the dispatch note.',
            'image_url' => $imageUrl,
        ])->assertOk()
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Admin->value)
            ->assertJsonPath('data.message.image_url', $imageUrl);

        $this->assertDatabaseHas('support_messages', [
            'support_conversation_id' => $conversation->id,
            'body' => 'Please share a photo of the dispatch note.',
            'image_url' => $imageUrl,
        ]);
    }

    /* ----------------------------------------------------------------- */
    /* Vendor side */
    /* ----------------------------------------------------------------- */

    public function test_vendor_can_list_own_order_queries_with_order_context(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();
        $this->makeOrderQuery($admin, $vendor, $order);

        Sanctum::actingAs($vendor->user);

        $this->getJson('/api/v1/vendor/order-queries')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.order.order_number', $order->order_number)
            ->assertJsonPath('data.data.0.order.vendor_order_id', $order->vendorOrders()->where('vendor_id', $vendor->id)->value('id'))
            ->assertJsonPath('data.data.0.unread_for_vendor', 1);
    }

    public function test_vendor_can_open_and_reply_to_an_order_query_without_the_bot(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();
        $conversation = $this->makeOrderQuery($admin, $vendor, $order);

        Sanctum::actingAs($vendor->user);

        $this->getJson("/api/v1/vendor/order-queries/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.conversation.id', $conversation->id);

        $this->postJson("/api/v1/vendor/order-queries/{$conversation->id}/messages", [
            'body' => 'We have 42 units left.',
        ])->assertOk()
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Vendor->value)
            ->assertJsonPath('data.message.is_bot', false)
            ->assertJsonMissingPath('data.bot_reply');
    }

    public function test_admin_order_query_thread_show_includes_its_order_items_and_totals(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'product_name' => 'Steel Flask',
            'quantity' => 2,
            'total' => 900.00,
        ]);
        $conversation = $this->makeOrderQuery($admin, $vendor, $order);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/admin/order-queries/{$conversation->id}")->assertOk();

        $orderPayload = $response->json('data.conversation.order');
        $this->assertSame('Steel Flask', $orderPayload['items'][0]['product_name']);
        $this->assertSame(2, $orderPayload['items'][0]['quantity']);
        $this->assertSame(900, $orderPayload['items'][0]['total']);
        $this->assertArrayHasKey('subtotal', $orderPayload);
        $this->assertArrayHasKey('fees_total', $orderPayload);
        $this->assertArrayHasKey('shipping_charge', $orderPayload);
    }

    public function test_vendor_order_query_thread_show_includes_its_order_items_and_totals(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'product_name' => 'Cotton Kurta',
            'quantity' => 1,
            'total' => 1200.00,
        ]);
        $conversation = $this->makeOrderQuery($admin, $vendor, $order);

        Sanctum::actingAs($vendor->user);

        $response = $this->getJson("/api/v1/vendor/order-queries/{$conversation->id}")->assertOk();

        $orderPayload = $response->json('data.conversation.order');
        $this->assertSame('Cotton Kurta', $orderPayload['items'][0]['product_name']);
        $this->assertSame(1, $orderPayload['items'][0]['quantity']);
        $this->assertSame(1200, $orderPayload['items'][0]['total']);
        $this->assertSame($order->order_number, $orderPayload['order_number']);
        $this->assertArrayHasKey('total', $orderPayload);
    }

    public function test_vendor_can_reply_to_an_order_query_with_an_image(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();
        $conversation = $this->makeOrderQuery($admin, $vendor, $order);

        Sanctum::actingAs($vendor->user);

        $imageUrl = 'https://example.com/uploads/images/2026-10/stock.png';

        $this->postJson("/api/v1/vendor/order-queries/{$conversation->id}/messages", [
            'body' => 'See the attached photo of the stock.',
            'image_url' => $imageUrl,
        ])->assertOk()
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Vendor->value)
            ->assertJsonPath('data.message.image_url', $imageUrl);

        $this->assertDatabaseHas('support_messages', [
            'support_conversation_id' => $conversation->id,
            'body' => 'See the attached photo of the stock.',
            'image_url' => $imageUrl,
        ]);
    }

    public function test_order_query_reply_rejects_an_invalid_image_url(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();
        $conversation = $this->makeOrderQuery($admin, $vendor, $order);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/order-queries/{$conversation->id}/messages", [
            'body' => 'Please share a photo.',
            'image_url' => 'not-a-url',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('image_url');

        $this->assertDatabaseMissing('support_messages', [
            'support_conversation_id' => $conversation->id,
            'body' => 'Please share a photo.',
        ]);
    }

    public function test_vendor_cannot_read_another_vendors_order_query(): void
    {
        [$admin, , $order] = $this->queryableOrder();
        $vendor = VendorProfile::factory()->approved()->create();

        $conversation = SupportConversation::query()->create([
            'kind' => SupportConversationKind::OrderQuery->value,
            'requester_type' => 'VENDOR',
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'vendor_user_id' => $vendor->user_id,
        ]);
        $conversation->messages()->create([
            'sender_user_id' => $admin->id,
            'sender_role' => SupportSenderRole::Admin->value,
            'body' => 'How many units are in stock?',
        ]);

        $otherVendor = VendorProfile::factory()->approved()->create();
        Sanctum::actingAs($otherVendor->user);

        $this->getJson("/api/v1/vendor/order-queries/{$conversation->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('conversation');

        $this->postJson("/api/v1/vendor/order-queries/{$conversation->id}/messages", [
            'body' => 'Sneaky reply.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('conversation');
    }

    public function test_vendor_order_query_unread_count_excludes_general_support(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();
        $conversation = $this->makeOrderQuery($admin, $vendor, $order);

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

        $this->getJson('/api/v1/vendor/order-queries/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->getJson('/api/v1/vendor/support/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $conversation->messages()->update(['read_at' => now()]);

        $this->getJson('/api/v1/vendor/order-queries/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_vendor_marking_query_read_clears_the_badge(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();
        $conversation = $this->makeOrderQuery($admin, $vendor, $order);

        Sanctum::actingAs($vendor->user);

        $this->postJson("/api/v1/vendor/order-queries/{$conversation->id}/read")
            ->assertOk();

        $this->getJson('/api/v1/vendor/order-queries/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    /* ----------------------------------------------------------------- */
    /* Notifications */
    /* ----------------------------------------------------------------- */

    public function test_admin_reply_to_an_order_query_notifies_the_vendor_again(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();
        $conversation = $this->makeOrderQuery($admin, $vendor, $order);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/order-queries/{$conversation->id}/messages", [
            'body' => 'Please share the dispatch date.',
        ])->assertOk();

        $this->assertDatabaseMissing('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $vendor->user->id,
            'data->type' => NotificationType::OrderQueryForVendor->value,
        ]);
    }

    public function test_vendor_reply_to_an_order_query_notifies_the_admin_team(): void
    {
        [$admin, $vendor, $order] = $this->queryableOrder();
        $conversation = $this->makeOrderQuery($admin, $vendor, $order);

        Sanctum::actingAs($vendor->user);

        $this->postJson("/api/v1/vendor/order-queries/{$conversation->id}/messages", [
            'body' => 'We have 42 units left.',
        ])->assertOk();

        $this->assertDatabaseMissing('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $admin->id,
            'data->type' => NotificationType::OrderQueryReplyForAdmin->value,
        ]);
    }

    /* ----------------------------------------------------------------- */
    /* Helpers */
    /* ----------------------------------------------------------------- */

    /**
     * An admin, an approved vendor and an order the vendor is fulfilling.
     *
     * @return array{0: User, 1: VendorProfile, 2: Order}
     */
    private function queryableOrder(): array
    {
        $admin = User::factory()->superAdmin()->create();
        $vendor = VendorProfile::factory()->approved()->create();

        $order = Order::factory()->create(['user_id' => User::factory()->customer()->create()]);

        VendorOrder::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Confirmed->value,
        ]);

        return [$admin, $vendor, $order];
    }

    private function makeOrderQuery(User $admin, VendorProfile $vendor, Order $order): SupportConversation
    {
        $conversation = SupportConversation::query()->create([
            'kind' => SupportConversationKind::OrderQuery->value,
            'requester_type' => 'VENDOR',
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'vendor_user_id' => $vendor->user_id,
            'subject' => 'Stock confirmation needed',
        ]);

        $conversation->messages()->create([
            'sender_user_id' => $admin->id,
            'sender_role' => SupportSenderRole::Admin->value,
            'body' => 'Can you confirm how many units remain in stock?',
        ]);

        return $conversation;
    }
}
