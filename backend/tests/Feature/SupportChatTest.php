<?php

namespace Tests\Feature;

use App\Enums\SupportSenderRole;
use App\Enums\VendorCommissionStatus;
use App\Enums\VendorPayoutStatus;
use App\Events\SupportMessageSent;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\VendorCommission;
use App\Models\VendorPayout;
use App\Models\VendorProfile;
use App\Services\Support\SupportBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    use RefreshDatabase;

    /* ----------------------------------------------------------------- */
    /* Vendor side: opening the thread */
    /* ----------------------------------------------------------------- */

    public function test_vendor_opening_support_creates_their_single_conversation(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        Sanctum::actingAs($vendor->user);

        $response = $this->getJson('/api/v1/vendor/support');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.conversation.vendor_id', $vendor->id)
            ->assertJsonPath('data.messages.data', []);

        $this->assertDatabaseCount('support_conversations', 1);
        $this->assertDatabaseHas('support_conversations', [
            'vendor_id' => $vendor->id,
            'vendor_user_id' => $vendor->user_id,
        ]);
    }

    public function test_vendor_reopening_support_reuses_the_same_conversation(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        Sanctum::actingAs($vendor->user);

        $first = $this->getJson('/api/v1/vendor/support')->json('data.conversation.id');
        $second = $this->getJson('/api/v1/vendor/support')->json('data.conversation.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('support_conversations', 1);
    }

    public function test_support_suggests_help_topics(): void
    {
        Sanctum::actingAs(VendorProfile::factory()->approved()->create()->user);

        $response = $this->getJson('/api/v1/vendor/support');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.suggestions'));
    }

    public function test_customer_cannot_open_the_support_thread(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/v1/vendor/support')->assertForbidden();
    }

    public function test_guest_cannot_open_the_support_thread(): void
    {
        $this->getJson('/api/v1/vendor/support')->assertUnauthorized();
    }

    /* ----------------------------------------------------------------- */
    /* Vendor side: sending messages and the bot answering */
    /* ----------------------------------------------------------------- */

    public function test_vendor_message_is_stored_and_broadcast(): void
    {
        Event::fake([SupportMessageSent::class]);

        $vendor = VendorProfile::factory()->approved()->create();
        Sanctum::actingAs($vendor->user);

        $this->postJson('/api/v1/vendor/support/messages', [
            'body' => 'Please have a human look at my problem.',
            'skip_bot' => true,
        ])->assertOk()
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Vendor->value)
            ->assertJsonPath('data.bot_reply', null);

        $this->assertDatabaseHas('support_messages', [
            'sender_user_id' => $vendor->user_id,
            'sender_role' => SupportSenderRole::Vendor->value,
            'body' => 'Please have a human look at my problem.',
            'is_bot' => false,
        ]);

        Event::assertDispatched(SupportMessageSent::class, 1);
    }

    public function test_message_body_is_required(): void
    {
        Sanctum::actingAs(VendorProfile::factory()->approved()->create()->user);

        $this->postJson('/api/v1/vendor/support/messages', ['body' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');
    }

    public function test_bot_answers_a_recognised_question_without_a_human(): void
    {
        $vendor = VendorProfile::factory()->approved()->create([
            'commission_rate' => 12,
        ]);

        Sanctum::actingAs($vendor->user);

        $response = $this->postJson('/api/v1/vendor/support/messages', [
            'body' => 'What is my commission rate?',
        ])->assertOk();

        $botBody = $response->json('data.bot_reply.body');

        $this->assertNotNull($botBody);
        $this->assertStringContainsString('12.00%', $botBody);

        $this->assertDatabaseHas('support_messages', [
            'sender_role' => SupportSenderRole::Bot->value,
            'is_bot' => true,
        ]);
    }

    public function test_bot_reads_the_vendors_live_wallet_balance(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();

        $vendor->wallet()->create([
            'available_balance' => 4321.50,
            'pending_balance' => 250,
            'paid_total' => 900,
        ]);

        Sanctum::actingAs($vendor->user);

        $botBody = $this->postJson('/api/v1/vendor/support/messages', [
            'body' => 'what is my wallet balance',
        ])->assertOk()->json('data.bot_reply.body');

        $this->assertStringContainsString('4,321.50', $botBody);
        $this->assertStringContainsString('250.00', $botBody);
    }

    public function test_bot_explains_a_rejected_payout_using_the_admin_note(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();

        VendorPayout::factory()->create([
            'vendor_id' => $vendor->id,
            'amount' => 1500,
            'status' => VendorPayoutStatus::Rejected,
            'admin_note' => 'Bank account number did not match the store name.',
        ]);

        Sanctum::actingAs($vendor->user);

        $botBody = $this->postJson('/api/v1/vendor/support/messages', [
            'body' => 'why was my payout rejected?',
        ])->assertOk()->json('data.bot_reply.body');

        $this->assertStringContainsString('Bank account number did not match', $botBody);
        $this->assertStringContainsString('1,500.00', $botBody);
    }

    public function test_bot_reports_the_next_return_window_release(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();

        VendorCommission::factory()->create([
            'vendor_id' => $vendor->id,
            'status' => VendorCommissionStatus::Pending,
            'return_window_ends_at' => now()->addDays(3),
            'settled_at' => null,
        ]);

        Sanctum::actingAs($vendor->user);

        $botBody = $this->postJson('/api/v1/vendor/support/messages', [
            'body' => 'settlement question here',
        ])->assertOk()->json('data.bot_reply.body');

        $this->assertStringContainsString(now()->addDays(3)->format('d M Y'), $botBody);
    }

    public function test_bot_explains_a_pending_vendor_approval(): void
    {
        // Pending vendors are blocked by the approved.vendor middleware, so the
        // answer is asserted on the bot itself rather than through the endpoint.
        $vendor = VendorProfile::factory()->pending()->create();

        $reply = app(SupportBot::class)->replyTo('what is my account status', $vendor->user);

        $this->assertStringContainsString('pending approval', $reply);
        $this->assertStringContainsString($vendor->store_name, $reply);
    }

    public function test_unrecognised_question_hands_off_to_a_human(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        Sanctum::actingAs($vendor->user);

        $botBody = $this->postJson('/api/v1/vendor/support/messages', [
            'body' => 'my neighbour stole my forklift and I need a favour',
        ])->assertOk()->json('data.bot_reply.body');

        $this->assertStringContainsString('support team', $botBody);
    }

    public function test_bot_can_be_skipped_per_message(): void
    {
        Sanctum::actingAs(VendorProfile::factory()->approved()->create()->user);

        $response = $this->postJson('/api/v1/vendor/support/messages', [
            'body' => 'What is my commission rate?',
            'skip_bot' => true,
        ])->assertOk();

        $this->assertNull($response->json('data.bot_reply'));
    }

    public function test_bot_reply_counts_as_support_having_replied(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        Sanctum::actingAs($vendor->user);

        $this->postJson('/api/v1/vendor/support/messages', [
            'body' => 'what is my commission rate?',
        ])->assertOk();

        $conversation = SupportConversation::query()->where('vendor_id', $vendor->id)->firstOrFail();

        $this->assertNotNull($conversation->last_requester_reply_at);
        $this->assertNotNull($conversation->last_admin_reply_at);
        $this->assertFalse($conversation->isWaitingOnAdmin());
    }

    /* ----------------------------------------------------------------- */
    /* Read state */
    /* ----------------------------------------------------------------- */

    public function test_vendor_reading_marks_admin_messages_as_read(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        Sanctum::actingAs($vendor->user);

        $conversation = SupportConversation::factory()->create([
            'vendor_id' => $vendor->id,
            'vendor_user_id' => $vendor->user_id,
        ]);

        SupportMessage::factory()->fromAdmin()->create([
            'support_conversation_id' => $conversation->id,
        ]);
        SupportMessage::factory()->fromAdmin()->create([
            'support_conversation_id' => $conversation->id,
        ]);
        SupportMessage::factory()->create([
            'support_conversation_id' => $conversation->id,
        ]);

        $response = $this->postJson('/api/v1/vendor/support/read')->assertOk();

        $this->assertSame(2, $response->json('data.marked'));
        $this->assertSame(
            0,
            $conversation->messages()->unreadFor(SupportSenderRole::Vendor->value)->count(),
        );
    }

    public function test_vendor_unread_count_ignores_their_own_messages(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        Sanctum::actingAs($vendor->user);

        $conversation = SupportConversation::factory()->create([
            'vendor_id' => $vendor->id,
            'vendor_user_id' => $vendor->user_id,
        ]);

        SupportMessage::factory()->create(['support_conversation_id' => $conversation->id]);
        SupportMessage::factory()->fromBot()->create(['support_conversation_id' => $conversation->id]);

        $this->getJson('/api/v1/vendor/support/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);
    }

    /* ----------------------------------------------------------------- */
    /* Admin side: the inbox */
    /* ----------------------------------------------------------------- */

    public function test_admin_counts_endpoint_returns_badges_without_the_list(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        SupportConversation::factory()->awaitingAdmin()->create();
        SupportConversation::factory()->create([
            'last_requester_reply_at' => now()->subHour(),
            'last_admin_reply_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/support/counts')->assertOk();

        $response->assertJsonPath('data.counts.awaiting_admin', 1)
            ->assertJsonPath('data.counts.unread_messages', 0)
            // Deliberately not a conversation list: the sidebar polls this often.
            ->assertJsonMissingPath('data.data');
    }

    public function test_admin_counts_count_unread_requester_messages_until_the_admin_reads_them(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $conversation = SupportConversation::factory()->awaitingAdmin()->create();
        $conversation->messages()->create([
            'sender_user_id' => $conversation->vendor_user_id,
            'sender_role' => SupportSenderRole::Vendor->value,
            'body' => 'Still waiting on you.',
        ]);

        $this->getJson('/api/v1/admin/support/counts')
            ->assertOk()
            ->assertJsonPath('data.counts.awaiting_admin', 1)
            ->assertJsonPath('data.counts.unread_messages', 1);

        // Opening the thread is what clears the requester's message from the badge.
        $this->postJson("/api/v1/admin/support/{$conversation->id}/read")->assertOk();

        $this->getJson('/api/v1/admin/support/counts')
            ->assertOk()
            ->assertJsonPath('data.counts.awaiting_admin', 1)
            ->assertJsonPath('data.counts.unread_messages', 0);
    }

    public function test_admin_counts_are_not_reachable_by_a_vendor(): void
    {
        Sanctum::actingAs(VendorProfile::factory()->approved()->create()->user);

        $this->getJson('/api/v1/admin/support/counts')->assertForbidden();
    }

    public function test_admin_lists_vendor_conversations_newest_first(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $older = SupportConversation::factory()->create([
            'last_message_at' => now()->subHour(),
        ]);
        $newer = SupportConversation::factory()->create([
            'last_message_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/support')->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id')->all();

        $this->assertSame([$newer->id, $older->id], $ids);
    }

    public function test_inbox_rows_carry_the_newest_message_as_a_preview(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $conversation = SupportConversation::factory()->create();

        SupportMessage::factory()->fromAdmin()->create([
            'support_conversation_id' => $conversation->id,
            'body' => 'An earlier admin reply.',
        ]);
        SupportMessage::factory()->create([
            'support_conversation_id' => $conversation->id,
            'body' => 'The newest thing the vendor said.',
        ]);

        $response = $this->getJson('/api/v1/admin/support')->assertOk();

        $row = collect($response->json('data.data'))->firstWhere('id', $conversation->id);

        $this->assertNotNull($row['latest_message']);
        $this->assertSame('The newest thing the vendor said.', $row['latest_message']['body']);
        $this->assertSame(SupportSenderRole::Vendor->value, $row['latest_message']['sender_role']);
    }

    public function test_admin_inbox_reports_threads_waiting_on_them(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        SupportConversation::factory()->awaitingAdmin()->create();
        SupportConversation::factory()->create([
            'last_requester_reply_at' => now()->subHour(),
            'last_admin_reply_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/support')->assertOk();

        $this->assertSame(1, $response->json('data.counts.awaiting_admin'));
    }

    public function test_admin_inbox_can_be_filtered_to_awaiting_threads(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $waiting = SupportConversation::factory()->awaitingAdmin()->create();
        SupportConversation::factory()->create([
            'last_requester_reply_at' => now()->subHour(),
            'last_admin_reply_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/support?filter=awaiting')->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id')->all();

        $this->assertSame([$waiting->id], $ids);
    }

    public function test_closed_conversations_are_hidden_from_the_default_inbox(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        SupportConversation::factory()->closed()->create();

        $this->assertCount(0, $this->getJson('/api/v1/admin/support')->json('data.data'));

        $this->assertCount(
            1,
            $this->getJson('/api/v1/admin/support?filter=closed')->json('data.data'),
        );
    }

    public function test_vendor_cannot_reach_the_admin_inbox(): void
    {
        Sanctum::actingAs(VendorProfile::factory()->approved()->create()->user);

        $this->getJson('/api/v1/admin/support')->assertForbidden();
    }

    /* ----------------------------------------------------------------- */
    /* Admin side: replying, closing, reopening */
    /* ----------------------------------------------------------------- */

    public function test_admin_reply_is_stored_and_marked_read(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $conversation = SupportConversation::factory()->awaitingAdmin()->create();

        SupportMessage::factory()->create([
            'support_conversation_id' => $conversation->id,
        ]);

        $this->postJson("/api/v1/admin/support/{$conversation->id}/messages", [
            'body' => 'Looking into this now.',
        ])->assertOk()
            ->assertJsonPath('data.message.sender_role', SupportSenderRole::Admin->value)
            ->assertJsonPath('data.message.sender_name', $admin->name);

        $this->assertSame(
            0,
            $conversation->messages()->unreadFor(SupportSenderRole::Admin->value)->count(),
        );
    }

    public function test_admin_reply_updates_the_conversation_timestamps(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $conversation = SupportConversation::factory()->awaitingAdmin()->create();

        $this->postJson("/api/v1/admin/support/{$conversation->id}/messages", [
            'body' => 'On it.',
        ])->assertOk();

        $conversation->refresh();

        $this->assertFalse($conversation->isWaitingOnAdmin());
        $this->assertNotNull($conversation->last_admin_reply_at);
    }

    public function test_admin_can_close_and_reopen_a_conversation(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $conversation = SupportConversation::factory()->create();

        $this->putJson("/api/v1/admin/support/{$conversation->id}/close")
            ->assertOk()
            ->assertJsonPath('data.conversation.is_closed', true);

        $this->assertNotNull($conversation->fresh()->closed_at);

        $this->putJson("/api/v1/admin/support/{$conversation->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.conversation.is_closed', false);

        $this->assertNull($conversation->fresh()->closed_at);
    }

    public function test_reopening_an_open_conversation_fails(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $conversation = SupportConversation::factory()->create();

        $this->putJson("/api/v1/admin/support/{$conversation->id}/reopen")
            ->assertStatus(422);
    }

    public function test_vendor_message_reopens_a_closed_conversation(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        Sanctum::actingAs($vendor->user);

        SupportConversation::factory()->closed()->create([
            'vendor_id' => $vendor->id,
            'vendor_user_id' => $vendor->user_id,
        ]);

        $this->postJson('/api/v1/vendor/support/messages', [
            'body' => 'Actually I still need help.',
            'skip_bot' => true,
        ])->assertOk();

        $conversation = SupportConversation::where('vendor_id', $vendor->id)->firstOrFail();

        $this->assertFalse($conversation->is_closed);
        $this->assertNull($conversation->closed_at);
    }

    public function test_admin_can_read_a_conversation_transcript(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $conversation = SupportConversation::factory()->create();

        SupportMessage::factory()->create(['support_conversation_id' => $conversation->id]);
        SupportMessage::factory()->fromAdmin()->create(['support_conversation_id' => $conversation->id]);

        $response = $this->getJson("/api/v1/admin/support/{$conversation->id}")->assertOk();

        $this->assertCount(2, $response->json('data.messages.data'));
    }

    /* ----------------------------------------------------------------- */
    /* Channel authorisation */
    /* ----------------------------------------------------------------- */

    public function test_channel_authorises_the_owning_vendor_and_any_admin(): void
    {
        $owner = VendorProfile::factory()->approved()->create();
        $stranger = VendorProfile::factory()->approved()->create();
        $conversation = SupportConversation::factory()->create([
            'vendor_id' => $owner->id,
            'vendor_user_id' => $owner->user_id,
        ]);

        $channel = "private-support.conversation.{$conversation->id}";

        $this->assertTrue($this->channelAllows($owner->user, $channel));
        $this->assertFalse($this->channelAllows($stranger->user, $channel));
        $this->assertTrue($this->channelAllows(User::factory()->superAdmin()->create(), $channel));
        $this->assertFalse($this->channelAllows(User::factory()->customer()->create(), $channel));
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

    public function test_bot_intent_matching_prefers_the_more_specific_rule(): void
    {
        $bot = app(SupportBot::class);
        $vendorUser = VendorProfile::factory()->approved()->create()->user;

        $rejected = $bot->replyTo('why was my payout rejected', $vendorUser);
        $pending = $bot->replyTo('where is my payout', $vendorUser);

        $this->assertStringContainsString('rejected', mb_strtolower($rejected));
        $this->assertStringNotContainsString('rejected', mb_strtolower($pending));
    }
}
