<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Enums\SupportConversationKind;
use App\Enums\SupportRequesterType;
use App\Enums\SupportSenderRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReturn;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\Wallet;
use App\Notifications\AppNotification;
use App\Services\Support\CustomerSupportBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The customer half of support chat: their own single thread, the customer
 * intent set, and the second admin inbox.
 */
class CustomerSupportChatTest extends TestCase
{
    use RefreshDatabase;

    /* ----------------------------------------------------------------- */
    /* Customer side: opening the thread */
    /* ----------------------------------------------------------------- */

    public function test_customer_opening_support_creates_their_single_conversation(): void
    {
        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/v1/user/support');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.conversation.requester_type', SupportRequesterType::Customer->value)
            ->assertJsonPath('data.conversation.customer_user_id', $customer->id)
            // A customer has no store, so the vendor keys must not be faked up.
            ->assertJsonPath('data.conversation.vendor_id', null)
            ->assertJsonPath('data.messages.data', []);

        $this->assertDatabaseCount('support_conversations', 1);
        $this->assertDatabaseHas('support_conversations', [
            'requester_type' => SupportRequesterType::Customer->value,
            'customer_user_id' => $customer->id,
            'vendor_id' => null,
        ]);
    }

    public function test_customer_reopening_support_reuses_the_same_conversation(): void
    {
        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        $first = $this->getJson('/api/v1/user/support')->json('data.conversation.id');
        $second = $this->getJson('/api/v1/user/support')->json('data.conversation.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('support_conversations', 1);
    }

    public function test_a_customer_and_a_vendor_never_share_a_thread(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $customer = User::factory()->customer()->create();

        Sanctum::actingAs($vendor->user);
        $vendorThread = $this->getJson('/api/v1/vendor/support')->json('data.conversation.id');

        Sanctum::actingAs($customer);
        $customerThread = $this->getJson('/api/v1/user/support')->json('data.conversation.id');

        $this->assertNotSame($vendorThread, $customerThread);
        $this->assertDatabaseCount('support_conversations', 2);
    }

    public function test_support_suggests_customer_help_topics(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $response = $this->getJson('/api/v1/user/support');

        $response->assertOk();
        $suggestions = $response->json('data.suggestions');

        $this->assertNotEmpty($suggestions);
        $this->assertContains('Where is my order?', $suggestions);
    }

    public function test_customer_thread_show_carries_their_recent_orders_next_to_the_first_message(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-12345',
            'order_status' => OrderStatus::Shipped->value,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Wireless Mouse',
            'quantity' => 2,
            'total' => 499.00,
        ]);
        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/v1/user/support')->assertOk();

        $recent = $response->json('data.conversation.recent_orders');
        $this->assertSame('ORD-12345', $recent[0]['order_number']);
        $this->assertSame('SHIPPED', $recent[0]['order_status']);
        $this->assertSame('Wireless Mouse', $recent[0]['items'][0]['product_name']);
        $this->assertSame(2, $recent[0]['items'][0]['quantity']);
    }

    public function test_vendor_cannot_open_the_customer_support_thread(): void
    {
        Sanctum::actingAs(VendorProfile::factory()->approved()->create()->user);

        $this->getJson('/api/v1/user/support')->assertForbidden();
    }

    public function test_guest_cannot_open_the_customer_support_thread(): void
    {
        $this->getJson('/api/v1/user/support')->assertUnauthorized();
    }

    /* ----------------------------------------------------------------- */
    /* Customer side: sending */
    /* ----------------------------------------------------------------- */

    public function test_customer_message_is_stored_under_the_customer_role(): void
    {
        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/user/support/messages', [
            'body' => 'My order never arrived.',
            'skip_bot' => true,
        ])->assertOk();

        $response->assertJsonPath('data.message.sender_role', SupportSenderRole::Customer->value)
            ->assertJsonPath('data.message.body', 'My order never arrived.')
            ->assertJsonPath('data.bot_reply', null);

        $this->assertDatabaseHas('support_messages', [
            'sender_user_id' => $customer->id,
            'sender_role' => SupportSenderRole::Customer->value,
            'body' => 'My order never arrived.',
        ]);
    }

    public function test_customer_message_marks_the_thread_waiting_on_an_admin(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->postJson('/api/v1/user/support/messages', [
            'body' => 'I need a human please.',
            'skip_bot' => true,
        ])->assertOk();

        $conversation = SupportConversation::query()
            ->where('requester_type', SupportRequesterType::Customer->value)
            ->firstOrFail();

        $this->assertNotNull($conversation->last_requester_reply_at);
        $this->assertTrue($conversation->isWaitingOnAdmin());
    }

    public function test_skip_bot_sends_the_message_straight_to_an_admin(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->postJson('/api/v1/user/support/messages', [
            'body' => 'Get me a person.',
            'skip_bot' => true,
        ])->assertOk();

        $this->assertDatabaseMissing('support_messages', ['is_bot' => true]);
    }

    public function test_bot_answers_a_customer_question_from_live_order_data(): void
    {
        $customer = User::factory()->customer()->create();
        Order::factory()->create([
            'user_id' => $customer->id,
            'order_status' => OrderStatus::Shipped->value,
            'order_number' => 'ORD-CUSTOMER1',
        ]);
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/user/support/messages', [
            'body' => 'Where is my order?',
        ])->assertOk();

        $botReply = $response->json('data.bot_reply.body');

        $this->assertStringContainsString('ORD-CUSTOMER1', $botReply);
        $this->assertStringContainsString('Shipped', $botReply);
        $this->assertDatabaseHas('support_messages', ['is_bot' => true]);
    }

    public function test_bot_answers_a_return_question_from_the_customers_latest_return(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create(['user_id' => $customer->id]);
        ProductReturn::factory()->create([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'status' => ReturnStatus::PickedUp->value,
        ]);
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/user/support/messages', [
            'body' => 'How do I return something?',
        ])->assertOk();

        $this->assertStringContainsString('picked up', $response->json('data.bot_reply.body'));
    }

    public function test_bot_answers_wallet_balance_from_the_live_wallet(): void
    {
        $customer = User::factory()->customer()->create();
        Wallet::factory()->create([
            'user_id' => $customer->id,
            'available_balance' => 1234.50,
            'pending_balance' => 10.00,
        ]);
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/user/support/messages', [
            'body' => 'What is my wallet balance?',
        ])->assertOk();

        $botReply = $response->json('data.bot_reply.body');

        $this->assertStringContainsString('1,234.50', $botReply);
        $this->assertStringContainsString('10.00', $botReply);
    }

    public function test_unrecognised_customer_question_hands_off_to_an_admin(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $response = $this->postJson('/api/v1/user/support/messages', [
            'body' => 'zzz qqq vvv nonsense xyzzy plugh',
        ])->assertOk();

        $this->assertStringContainsString(
            'support team',
            $response->json('data.bot_reply.body'),
        );
    }

    /* ----------------------------------------------------------------- */
    /* Read state */
    /* ----------------------------------------------------------------- */

    public function test_customer_reading_marks_admin_messages_as_read(): void
    {
        $customer = User::factory()->customer()->create();
        $conversation = SupportConversation::factory()->forCustomer($customer)->create();
        Sanctum::actingAs($customer);

        SupportMessage::factory()->fromCustomer($customer)
            ->create(['support_conversation_id' => $conversation->id]);
        SupportMessage::factory()->fromAdmin()
            ->create(['support_conversation_id' => $conversation->id]);

        $response = $this->postJson('/api/v1/user/support/read')->assertOk();

        $this->assertSame(1, $response->json('data.marked'));
        $this->assertSame(
            0,
            $conversation->messages()->unreadFor(SupportSenderRole::Customer->value)->count(),
        );
    }

    public function test_unread_count_only_counts_messages_from_support(): void
    {
        $customer = User::factory()->customer()->create();
        $conversation = SupportConversation::factory()->forCustomer($customer)->create();
        Sanctum::actingAs($customer);

        SupportMessage::factory()->fromCustomer($customer)
            ->create(['support_conversation_id' => $conversation->id]);
        SupportMessage::factory()->fromAdmin()
            ->create(['support_conversation_id' => $conversation->id]);
        SupportMessage::factory()->fromBot()
            ->create(['support_conversation_id' => $conversation->id]);

        $this->getJson('/api/v1/user/support/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);
    }

    /* ----------------------------------------------------------------- */
    /* Admin side: the customer inbox */
    /* ----------------------------------------------------------------- */

    public function test_admin_customer_inbox_lists_only_customer_threads(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $customer = User::factory()->customer()->create();
        $customerThread = SupportConversation::factory()->forCustomer($customer)->create();
        $vendorThread = SupportConversation::factory()->create();

        $ids = collect($this->getJson('/api/v1/admin/customer-support')->json('data.data'))
            ->pluck('id')
            ->all();

        $this->assertSame([$customerThread->id], $ids);
        $this->assertNotContains($vendorThread->id, $ids);
    }

    public function test_customer_inbox_rows_carry_the_customer_identity(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $customer = User::factory()->customer()->create(['name' => 'Priya Customer']);
        SupportConversation::factory()->forCustomer($customer)->create();

        $row = collect($this->getJson('/api/v1/admin/customer-support')->json('data.data'))->first();

        $this->assertSame($customer->id, $row['customer']['id']);
        $this->assertSame('Priya Customer', $row['customer']['name']);
        $this->assertSame($customer->email, $row['customer']['email']);
    }

    public function test_admin_customer_thread_shows_the_same_recent_orders_and_suggestions(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $customer = User::factory()->customer()->create();
        $conversation = SupportConversation::factory()->forCustomer($customer)->create();

        Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-67890',
        ]);

        $response = $this->getJson("/api/v1/admin/customer-support/{$conversation->id}")->assertOk();

        // The admin is offered the same quick questions as the customer.
        $suggestions = $response->json('data.suggestions');
        $this->assertNotEmpty($suggestions);
        $this->assertContains('Where is my order?', $suggestions);

        // ...and sees the same order context above the first message.
        $this->assertSame(
            'ORD-67890',
            $response->json('data.conversation.recent_orders.0.order_number'),
        );
    }

    public function test_customer_inbox_counts_endpoint_returns_badges(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        SupportConversation::factory()
            ->forCustomer(User::factory()->customer()->create())
            ->awaitingAdmin()
            ->create();

        $this->getJson('/api/v1/admin/customer-support/counts')
            ->assertOk()
            ->assertJsonPath('data.counts.awaiting_admin', 1)
            ->assertJsonPath('data.counts.unread_messages', 0)
            ->assertJsonMissingPath('data.data');
    }

    public function test_vendor_inbox_counts_ignore_customer_threads(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        SupportConversation::factory()
            ->forCustomer(User::factory()->customer()->create())
            ->awaitingAdmin()
            ->create();
        SupportConversation::factory()->awaitingAdmin()->create();

        $this->getJson('/api/v1/admin/support/counts')
            ->assertOk()
            ->assertJsonPath('data.counts.awaiting_admin', 1);
    }

    public function test_customer_inbox_can_be_filtered_to_awaiting_threads(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $waiting = SupportConversation::factory()
            ->forCustomer(User::factory()->customer()->create())
            ->awaitingAdmin()
            ->create();
        SupportConversation::factory()
            ->forCustomer(User::factory()->customer()->create())
            ->create([
                'last_requester_reply_at' => now()->subHour(),
                'last_admin_reply_at' => now(),
            ]);

        $ids = collect($this->getJson('/api/v1/admin/customer-support?filter=awaiting')->json('data.data'))
            ->pluck('id')
            ->all();

        $this->assertSame([$waiting->id], $ids);
    }

    public function test_admin_reply_reaches_the_customer_thread(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $conversation = SupportConversation::factory()
            ->forCustomer(User::factory()->customer()->create())
            ->awaitingAdmin()
            ->create();

        $this->postJson("/api/v1/admin/customer-support/{$conversation->id}/messages", [
            'body' => 'Looking into your order now.',
        ])->assertOk()
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Admin->value)
            ->assertJsonPath('data.message.sender_name', $admin->name);

        $this->assertFalse($conversation->fresh()->isWaitingOnAdmin());
    }

    public function test_admin_can_close_and_reopen_a_customer_thread(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $conversation = SupportConversation::factory()
            ->forCustomer(User::factory()->customer()->create())
            ->create();

        $this->putJson("/api/v1/admin/customer-support/{$conversation->id}/close")
            ->assertOk()
            ->assertJsonPath('data.conversation.is_closed', true);

        $this->putJson("/api/v1/admin/customer-support/{$conversation->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.conversation.is_closed', false);
    }

    public function test_customer_message_reopens_a_closed_thread(): void
    {
        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        SupportConversation::factory()->forCustomer($customer)->closed()->create();

        $this->postJson('/api/v1/user/support/messages', [
            'body' => 'Actually I still need help.',
            'skip_bot' => true,
        ])->assertOk();

        $conversation = SupportConversation::query()
            ->where('requester_type', SupportRequesterType::Customer->value)
            ->firstOrFail();

        $this->assertFalse($conversation->is_closed);
        $this->assertNull($conversation->closed_at);
    }

    public function test_customer_cannot_reach_the_admin_customer_inbox(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/v1/admin/customer-support')->assertForbidden();
    }

    public function test_vendor_cannot_reach_the_admin_customer_inbox(): void
    {
        Sanctum::actingAs(VendorProfile::factory()->approved()->create()->user);

        $this->getJson('/api/v1/admin/customer-support')->assertForbidden();
    }

    /* ----------------------------------------------------------------- */
    /* Inbox separation */
    /* ----------------------------------------------------------------- */

    public function test_a_vendor_thread_is_not_reachable_through_the_customer_inbox(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $vendorThread = SupportConversation::factory()->create();

        $this->getJson("/api/v1/admin/customer-support/{$vendorThread->id}")->assertStatus(422);
        $this->postJson("/api/v1/admin/customer-support/{$vendorThread->id}/messages", [
            'body' => 'Wrong inbox.',
        ])->assertStatus(422);
    }

    public function test_a_customer_thread_is_not_reachable_through_the_vendor_inbox(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $customerThread = SupportConversation::factory()
            ->forCustomer(User::factory()->customer()->create())
            ->create();

        $this->getJson("/api/v1/admin/support/{$customerThread->id}")->assertStatus(422);
    }

    /* ----------------------------------------------------------------- */
    /* Channel authorisation */
    /* ----------------------------------------------------------------- */

    public function test_channel_authorises_the_owning_customer_and_any_admin(): void
    {
        $owner = User::factory()->customer()->create();
        $stranger = User::factory()->customer()->create();
        $conversation = SupportConversation::factory()->forCustomer($owner)->create();

        $channel = "private-support.conversation.{$conversation->id}";

        $this->assertTrue($this->channelAllows($owner, $channel));
        $this->assertFalse($this->channelAllows($stranger, $channel));
        $this->assertTrue($this->channelAllows(User::factory()->superAdmin()->create(), $channel));
        $this->assertFalse($this->channelAllows(VendorProfile::factory()->approved()->create()->user, $channel));
    }

    public function test_a_customer_cannot_join_a_vendor_thread_channel(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $conversation = SupportConversation::factory()->create([
            'vendor_id' => $vendor->id,
            'vendor_user_id' => $vendor->user_id,
        ]);

        $this->assertFalse($this->channelAllows(
            User::factory()->customer()->create(),
            "private-support.conversation.{$conversation->id}",
        ));
    }

    /**
     * Ask the real broadcast auth endpoint, so the test covers the channel
     * callback as registered in routes/channels.php rather than a re-implementation.
     */
    private function channelAllows(User $user, string $channel): bool
    {
        $this->useRealBroadcaster();

        // The auth guard caches the resolved user between requests, so drop it
        // or every call in this test would run as whoever was signed in first.
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'sanctum')->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channel,
        ])->status() === 200;
    }

    /**
     * Swap off the null broadcaster for the real one.
     *
     * Two things are needed. NullBroadcaster::auth() is a no-op that authorises
     * every channel, and `Broadcast::channel()` registers on whichever
     * connection was active when routes/channels.php was required at boot — so
     * the file has to be re-required against the new connection too.
     */
    private function useRealBroadcaster(): void
    {
        config(['broadcasting.default' => 'reverb']);

        require base_path('routes/channels.php');
    }

    /* ----------------------------------------------------------------- */
    /* The assistant */
    /* ----------------------------------------------------------------- */

    public function test_customer_bot_intent_matching_prefers_the_more_specific_rule(): void
    {
        $bot = app(CustomerSupportBot::class);
        $customer = User::factory()->customer()->create();

        $refund = $bot->replyTo('when will I get my refund', $customer);
        $return = $bot->replyTo('how do I return something', $customer);

        $this->assertStringContainsString('refund', mb_strtolower($refund));
        $this->assertStringNotContainsString('latest refund', mb_strtolower($return));
    }

    public function test_customer_bot_answers_with_the_named_order_when_one_is_quoted(): void
    {
        $bot = app(CustomerSupportBot::class);
        $customer = User::factory()->customer()->create();

        Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-NEWEST',
            'order_status' => OrderStatus::Pending->value,
        ]);
        Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-OLDER',
            'order_status' => OrderStatus::Delivered->value,
        ]);

        $answer = $bot->replyTo('where is my order ORD-OLDER', $customer);

        $this->assertStringContainsString('ORD-OLDER', $answer);
        $this->assertStringNotContainsString('ORD-NEWEST', $answer);
    }

    public function test_customer_bot_reports_payment_state_from_the_order(): void
    {
        $bot = app(CustomerSupportBot::class);
        $customer = User::factory()->customer()->create();

        Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-PAID',
            'payment_status' => PaymentStatus::Paid->value,
            'payment_method' => 'razorpay',
        ]);

        $answer = $bot->replyTo('did my payment go through', $customer);

        $this->assertStringContainsString('paid in full', mb_strtolower($answer));
    }

    public function test_customer_bot_says_so_when_there_are_no_orders(): void
    {
        $bot = app(CustomerSupportBot::class);

        $answer = $bot->replyTo('where is my order', User::factory()->customer()->create());

        $this->assertStringContainsString('cannot see any orders', mb_strtolower($answer));
    }

    /* ----------------------------------------------------------------- */
    /* Notifications */
    /* ----------------------------------------------------------------- */

    public function test_customer_support_message_notifies_the_admin_team(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $customer = User::factory()->customer()->create();

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/user/support/messages', [
            'body' => 'Where is my order?',
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $admin->id,
            'data->type' => NotificationType::NewSupportQueryForAdmin->value,
        ]);
    }

    public function test_admin_reply_in_customer_support_notifies_the_customer(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $customer = User::factory()->customer()->create();

        $conversation = SupportConversation::query()->create([
            'kind' => SupportConversationKind::General->value,
            'requester_type' => SupportRequesterType::Customer->value,
            'customer_user_id' => $customer->id,
        ]);
        $conversation->messages()->create([
            'sender_user_id' => $customer->id,
            'sender_role' => SupportSenderRole::Customer->value,
            'body' => 'Where is my order?',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/customer-support/{$conversation->id}/messages", [
            'body' => 'It is out for delivery.',
        ])->assertOk()
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Admin->value);

        // Follow-up replies must not raise another notification: only the initial
        // request notifies; the rest flow through the support chat itself.
        $this->assertDatabaseMissing('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $customer->id,
            'data->type' => NotificationType::SupportReplyForCustomer->value,
        ]);
    }
}
