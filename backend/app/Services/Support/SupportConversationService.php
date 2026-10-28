<?php

namespace App\Services\Support;

use App\Enums\NotificationType;
use App\Enums\SupportConversationKind;
use App\Enums\SupportRequesterType;
use App\Enums\SupportSenderRole;
use App\Events\SupportMessageSent;
use App\Models\Order;
use App\Models\Product;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Owns the lifecycle of the single support conversation a requester has with the
 * marketplace team: creating it on first contact, recording messages, letting
 * the assistant answer, and telling both sides over the broadcast channel.
 *
 * A requester is either a vendor or a customer. Both get exactly one thread for
 * their lifetime, and both are answered by the same admin team — only the
 * assistant's knowledge differs, because a vendor asks about commission while a
 * customer asks about a delivery.
 */
class SupportConversationService
{
    public function __construct(
        private readonly SupportBot $vendorBot,
        private readonly CustomerSupportBot $customerBot,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * The vendor's conversation, created on first use.
     */
    public function findOrCreateForVendor(VendorProfile $vendor): SupportConversation
    {
        return SupportConversation::query()->firstOrCreate(
            [
                'kind' => SupportConversationKind::General->value,
                'requester_type' => SupportRequesterType::Vendor,
                'vendor_id' => $vendor->id,
            ],
            [
                'vendor_user_id' => $vendor->user_id,
            ],
        );
    }

    /**
     * The customer's conversation, created on first use.
     */
    public function findOrCreateForCustomer(User $customer): SupportConversation
    {
        return SupportConversation::query()->firstOrCreate(
            [
                'kind' => SupportConversationKind::General->value,
                'requester_type' => SupportRequesterType::Customer,
                'customer_user_id' => $customer->id,
            ],
        );
    }

    /**
     * The admin's order query thread for a vendor. One per (order, vendor):
     * asking again opens the existing thread rather than starting a duplicate.
     *
     * The caller adds the message, so reopening a thread never duplicates the
     * inquiry unless a new one is actually written.
     */
    public function findOrCreateOrderQuery(Order $order, VendorProfile $vendor): SupportConversation
    {
        return SupportConversation::query()->firstOrCreate(
            [
                'kind' => SupportConversationKind::OrderQuery->value,
                'requester_type' => SupportRequesterType::Vendor,
                'order_id' => $order->id,
                'vendor_id' => $vendor->id,
            ],
            [
                'vendor_user_id' => $vendor->user_id,
                'subject' => __('Order enquiry: :order', ['order' => $order->order_number]),
            ],
        );
    }

    /**
     * A customer's product query: a brand new thread every time (unlike order
     * queries there is no reusing — each enquiry stands alone), addressed to the
     * product's vendor and the admin team together. The caller notifies the
     * vendor; this method owns the thread.
     */
    public function createProductQuery(Product $product, User $customer, string $body, ?string $imageUrl = null): SupportConversation
    {
        $vendor = $product->vendor;

        $conversation = SupportConversation::query()->create([
            'kind' => SupportConversationKind::ProductQuery->value,
            'requester_type' => SupportRequesterType::Customer->value,
            'product_id' => $product->id,
            'vendor_id' => $vendor?->id,
            'vendor_user_id' => $vendor?->user_id,
            'customer_user_id' => $customer->id,
            'subject' => __('Product enquiry: :product', ['product' => $product->name]),
        ]);

        $this->record($conversation, $customer->id, SupportSenderRole::Customer, $body, imageUrl: $imageUrl);

        return $conversation;
    }

    /**
     * A product query the vendor starts themselves about one of their own
     * products, addressed to the marketplace team. There is no customer in the
     * thread, so it is a two-party vendor-to-team question sitting in the same
     * product-query inboxes. The caller notifies the admin team.
     */
    public function createVendorProductQuery(Product $product, VendorProfile $vendor, string $body, ?string $imageUrl = null): SupportConversation
    {
        $conversation = SupportConversation::query()->create([
            'kind' => SupportConversationKind::ProductQuery->value,
            'requester_type' => SupportRequesterType::Vendor->value,
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'vendor_user_id' => $vendor->user_id,
            'subject' => __('Product enquiry: :product', ['product' => $product->name]),
        ]);

        $this->record($conversation, $vendor->user_id, SupportSenderRole::Vendor, $body, imageUrl: $imageUrl);

        return $conversation;
    }

    /**
     * A product query the marketplace team starts about any product; the
     * product's vendor is the requester side, so the vendor's own list and the
     * admin product-query inbox both show it, exactly like an order query. The
     * caller notifies the vendor.
     */
    public function createAdminProductQuery(Product $product, User $admin, string $body, ?string $imageUrl = null): SupportConversation
    {
        $vendor = $product->vendor;

        $conversation = SupportConversation::query()->create([
            'kind' => SupportConversationKind::ProductQuery->value,
            'requester_type' => SupportRequesterType::Vendor->value,
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'vendor_user_id' => $vendor?->user_id,
            'subject' => __('Product enquiry: :product', ['product' => $product->name]),
        ]);

        $this->record($conversation, $admin->id, SupportSenderRole::Admin, $body, imageUrl: $imageUrl);

        return $conversation;
    }

    /**
     * Record a requester message and let the assistant reply when it recognises
     * the question.
     *
     * @return array{message: SupportMessage, bot_reply: ?SupportMessage}
     */
    public function postFromRequester(SupportConversation $conversation, User $requester, string $body, bool $askBot = true, ?string $imageUrl = null): array
    {
        $message = $this->record(
            $conversation,
            $requester->id,
            $conversation->requesterRole(),
            $body,
            imageUrl: $imageUrl,
        );

        $botReply = $askBot ? $this->replyWithBot($conversation, $body, $requester) : null;

        return ['message' => $message, 'bot_reply' => $botReply];
    }

    public function postFromAdmin(SupportConversation $conversation, User $admin, string $body, ?string $imageUrl = null): SupportMessage
    {
        return $this->record($conversation, $admin->id, SupportSenderRole::Admin, $body, imageUrl: $imageUrl);
    }

    /**
     * Record a product query reply from the vendor selling the product. Posted
     * under the vendor's own role so admin unread tracking and the requester's
     * unread badge see it as activity from the other side.
     */
    public function postFromVendor(SupportConversation $conversation, User $vendorUser, string $body, ?string $imageUrl = null): SupportMessage
    {
        return $this->record($conversation, $vendorUser->id, SupportSenderRole::Vendor, $body, imageUrl: $imageUrl);
    }

    /**
     * Answer a requester message from the app's own data, falling back to a
     * notice that a human has been looped in.
     */
    public function replyWithBot(SupportConversation $conversation, string $body, User $requester): SupportMessage
    {
        $answer = $conversation->requester_type === SupportRequesterType::Customer
            ? $this->customerBot->replyOrHandoff($body, $requester)
            : $this->vendorBot->replyOrHandoff($body, $requester);

        $botMessage = $this->record(
            $conversation,
            null,
            SupportSenderRole::Bot,
            $answer,
            isBot: true,
        );

        // The assistant speaks on the admin's behalf, so from the requester's
        // point of view support has replied and the thread is no longer waiting.
        $conversation->forceFill([
            'last_admin_reply_at' => $botMessage->created_at,
        ])->save();

        return $botMessage;
    }

    /**
     * Prompts to offer in the chat before the requester types anything.
     *
     * @return list<string>
     */
    public function suggestionsFor(SupportConversation $conversation): array
    {
        return $conversation->requester_type === SupportRequesterType::Customer
            ? $this->customerBot->suggestions()
            : $this->vendorBot->suggestions();
    }

    /**
     * Mark every message the given role has not read as read.
     */
    public function markReadFor(SupportConversation $conversation, SupportSenderRole $reader): int
    {
        return $conversation->messages()
            ->unreadFor($reader->value)
            ->update(['read_at' => now()]);
    }

    /**
     * Unread messages waiting on the given side of the conversation.
     */
    public function unreadCountFor(SupportConversation $conversation, SupportSenderRole $reader): int
    {
        return $conversation->messages()->unreadFor($reader->value)->count();
    }

    /**
     * Totals for an admin inbox badge, in one query each so the header does not
     * N+1 across every conversation.
     *
     * `$type` is null for the product-query inbox, which serves both customer-
     * and vendor-requester threads; every other inbox is scoped to one
     * requester type.
     *
     * awaiting_admin counts threads where the requester is waiting on a reply;
     * unread_messages counts requester messages the admin has not opened yet,
     * so it is the total of every row's per-conversation unread badge.
     *
     * @return array{awaiting_admin: int, unread_messages: int}
     */
    public function adminInboxCounts(?SupportRequesterType $type, SupportConversationKind $kind = SupportConversationKind::General): array
    {
        $open = SupportConversation::query()
            ->forKind($kind)
            ->when($type !== null, fn ($q) => $q->forRequesterType($type))
            ->where('is_closed', false);

        return [
            'open_count' => (clone $open)->count(),
            'awaiting_admin' => (clone $open)
                ->whereNotNull('last_requester_reply_at')
                ->where(function ($query): void {
                    $query->whereNull('last_admin_reply_at')
                        ->orWhereColumn('last_requester_reply_at', '>', 'last_admin_reply_at');
                })
                ->count(),
            'unread_messages' => SupportMessage::query()
                ->unreadFor(SupportSenderRole::Admin->value)
                ->whereHas('conversation', fn ($q) => $q
                    ->forKind($kind)
                    ->when($type !== null, fn ($t) => $t->forRequesterType($type))
                    ->where('is_closed', false))
                ->count(),
        ];
    }

    /**
     * Unread messages for one requester, used for their own chat badge.
     */
    public function unreadCountForRequester(SupportConversation $conversation): int
    {
        return $this->unreadCountFor($conversation, $conversation->requesterRole());
    }

    /**
     * Mark any in-app notifications tied to a conversation as read for the given
     * user, so the bell/badge stops alerting once they are actually viewing the
     * thread. Keeps notifications from lingering while the chat is open.
     */
    public function markNotificationsReadFor(User $user, int $conversationId): void
    {
        $user->notifications()
            ->whereNull('read_at')
            ->where('data->conversation_id', $conversationId)
            ->update(['read_at' => now()]);
    }

    /**
     * Unread messages for one vendor, used for the vendor's own chat badge.
     */
    public function vendorUnreadCount(VendorProfile $vendor): int
    {
        $conversation = SupportConversation::query()
            ->forKind(SupportConversationKind::General)
            ->forRequesterType(SupportRequesterType::Vendor)
            ->where('vendor_id', $vendor->id)
            ->first();

        return $conversation === null
            ? 0
            : $conversation->messages()->unreadFor(SupportSenderRole::Vendor->value)->count();
    }

    /**
     * Unread admin messages across every order query a vendor has, for the
     * order-query badge in the vendor sidebar.
     */
    public function orderQueryUnreadCountForVendor(VendorProfile $vendor): int
    {
        return SupportMessage::query()
            ->whereNull('read_at')
            ->where('sender_role', '!=', SupportSenderRole::Vendor->value)
            ->whereHas('conversation', fn ($q) => $q
                ->forKind(SupportConversationKind::OrderQuery)
                ->forRequesterType(SupportRequesterType::Vendor)
                ->where('vendor_id', $vendor->id))
            ->count();
    }

    /**
     * Unread customer and admin messages across every product query for one
     * vendor — customer-requester threads the vendor answers alongside the team,
     * and vendor-requester threads the vendor raised themselves — for the
     * product-query badge in the vendor sidebar.
     */
    public function productQueryUnreadCountForVendor(VendorProfile $vendor): int
    {
        return SupportMessage::query()
            ->whereNull('read_at')
            ->where('sender_role', '!=', SupportSenderRole::Vendor->value)
            ->whereHas('conversation', fn ($q) => $q
                ->forKind(SupportConversationKind::ProductQuery)
                ->where('vendor_id', $vendor->id))
            ->count();
    }

    /**
     * Unread messages across every product query a customer has opened, for
     * the product-query badge in their account sidebar.
     */
    public function customerProductQueryUnreadCountFor(User $customer): int
    {
        return SupportMessage::query()
            ->whereNull('read_at')
            ->where('sender_role', '!=', SupportSenderRole::Customer->value)
            ->whereHas('conversation', fn ($q) => $q
                ->forKind(SupportConversationKind::ProductQuery)
                ->forRequesterType(SupportRequesterType::Customer)
                ->where('customer_user_id', $customer->id))
            ->count();
    }

    /**
     * Store a message, refresh the conversation's activity timestamps and
     * broadcast to both sides.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function record(
        SupportConversation $conversation,
        ?int $senderUserId,
        SupportSenderRole $role,
        string $body,
        bool $isBot = false,
        ?string $imageUrl = null,
    ): SupportMessage {
        $requesterSide = $conversation->isRequesterSide($role);

        $message = DB::transaction(function () use ($conversation, $senderUserId, $role, $body, $isBot, $requesterSide, $imageUrl): SupportMessage {
            $message = $conversation->messages()->create([
                'sender_user_id' => $senderUserId,
                'sender_role' => $role->value,
                'body' => $body,
                'image_url' => $imageUrl,
                'is_bot' => $isBot,
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                'last_requester_reply_at' => $requesterSide
                    ? $message->created_at
                    : $conversation->last_requester_reply_at,
                'last_admin_reply_at' => $role->isStaff()
                    ? $message->created_at
                    : $conversation->last_admin_reply_at,
            ])->save();

            // A requester (or, on a product query, the vendor) coming back to a
            // closed thread reopens it rather than talking into a void.
            if ($conversation->is_closed && $requesterSide) {
                $conversation->forceFill(['is_closed' => false, 'closed_at' => null])->save();
            }

            return $message;
        });

        $message->loadMissing('sender');

        // The message is already stored; a websocket outage must not lose it or
        // fail the request, so real-time delivery is best-effort only.
        try {
            broadcast(new SupportMessageSent($conversation->fresh(), $message));
        } catch (Throwable $e) {
            Log::warning('Failed to broadcast support message.', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->notifyFor($conversation, $role, $isBot, $body);

        return $message;
    }

    /**
     * Push an in-app notification to the people a message matters to.
     *
     * The dispatch follows the conversation's kind and who is writing, so a
     * single funnel covers both creation and follow-up replies:
     *
     *  - an admin message answers the requester — their support chat, order or
     *    product query — so the requester (and, for a product query, the vendor
     *    who sells the product) is told a reply is waiting;
     *  - a requester message reaches out — a support query or a product query —
     *    so the admin team and/or the vendor is alerted;
     *  - the assistant's bot replies are shown inline and need no notification.
     *
     * The notification carries the message as a preview; the frontend deep-links
     * to the thread through the conversation_id in the payload.
     */
    private function notifyFor(
        SupportConversation $conversation,
        SupportSenderRole $role,
        bool $isBot,
        string $body,
    ): void {
        if ($isBot) {
            return;
        }

        // Only the very first message of a thread raises an in-app notification
        // (the initial request). Follow-up messages keep flowing through the
        // support chat (broadcast + polling) without spamming notifications.
        if ($conversation->messages()->count() > 1) {
            return;
        }

        $payload = ['conversation_id' => $conversation->id];
        $preview = mb_strimwidth($body, 0, 140, '…');

        if ($role === SupportSenderRole::Admin) {
            if ($conversation->kind === SupportConversationKind::General) {
                if ($conversation->requester_type === SupportRequesterType::Customer) {
                    $this->sendToCustomerUser(
                        $conversation,
                        NotificationType::SupportReplyForCustomer,
                        __('Support team replied'),
                        $preview,
                        $payload,
                    );
                } else {
                    $this->sendToVendorUser(
                        $conversation,
                        NotificationType::SupportReplyForVendor,
                        __('Support team replied'),
                        $preview,
                        $payload,
                    );
                }

                return;
            }

            if ($conversation->kind === SupportConversationKind::OrderQuery) {
                $isFirstAdminMessage = $conversation->messages()
                    ->where('sender_role', SupportSenderRole::Admin->value)
                    ->count() === 1;

                $this->sendToVendorUser(
                    $conversation,
                    NotificationType::OrderQueryForVendor,
                    $isFirstAdminMessage ? __('New order query') : __('New reply on order query'),
                    $preview,
                    $payload,
                );

                return;
            }

            // Product query: the answer matters to the requester, and the
            // vendor whose product is being asked about stays in the loop too.
            $this->sendToVendorUser(
                $conversation,
                NotificationType::ProductQueryForVendor,
                __('Product query reply'),
                $preview,
                $payload,
            );

            if ($conversation->requester_type === SupportRequesterType::Customer) {
                $this->sendToCustomerUser(
                    $conversation,
                    NotificationType::ProductQueryReplyForCustomer,
                    __('Reply on your product query'),
                    $preview,
                    $payload,
                );
            }

            return;
        }

        if ($role === SupportSenderRole::Vendor) {
            if ($conversation->kind === SupportConversationKind::OrderQuery) {
                $this->dispatcher->notifyAdmins(
                    NotificationType::OrderQueryReplyForAdmin,
                    __('Order query reply'),
                    $preview,
                    $payload,
                );
            } elseif ($conversation->kind === SupportConversationKind::ProductQuery) {
                $this->dispatcher->notifyAdmins(
                    NotificationType::ProductQueryForAdmin,
                    __('Product query reply'),
                    $preview,
                    $payload,
                );

                if ($conversation->requester_type === SupportRequesterType::Customer) {
                    $this->sendToCustomerUser(
                        $conversation,
                        NotificationType::ProductQueryReplyForCustomer,
                        __('Reply on your product query'),
                        $preview,
                        $payload,
                    );
                }
            } else {
                $this->dispatcher->notifyAdmins(
                    NotificationType::NewSupportQueryForAdmin,
                    __('New support message'),
                    $preview,
                    $payload + ['requester_type' => $conversation->requester_type->value],
                );
            }

            return;
        }

        // A customer message: a product query alerts the vendor who sells the
        // product and the admin team together, a general support query alerts
        // the admin team.
        if ($conversation->kind === SupportConversationKind::ProductQuery) {
            $this->sendToVendorUser(
                $conversation,
                NotificationType::ProductQueryForVendor,
                __('New product query'),
                $preview,
                $payload,
            );

            $this->dispatcher->notifyAdmins(
                NotificationType::ProductQueryForAdmin,
                __('New product query'),
                $preview,
                $payload,
            );
        } else {
            $this->dispatcher->notifyAdmins(
                NotificationType::NewSupportQueryForAdmin,
                __('New support query'),
                $preview,
                $payload + ['requester_type' => $conversation->requester_type->value],
            );
        }
    }

    private function sendToCustomerUser(
        SupportConversation $conversation,
        NotificationType $type,
        string $title,
        string $message,
        array $payload,
    ): void {
        $this->sendToUser($conversation->customerUser, $type, $title, $message, $payload);
    }

    private function sendToVendorUser(
        SupportConversation $conversation,
        NotificationType $type,
        string $title,
        string $message,
        array $payload,
    ): void {
        $this->sendToUser($conversation->vendorUser, $type, $title, $message, $payload);
    }

    private function sendToUser(
        ?User $user,
        NotificationType $type,
        string $title,
        string $message,
        array $payload,
    ): void {
        if ($user === null) {
            return;
        }

        $this->dispatcher->send($user, $type, $title, $message, $payload);
    }
}
