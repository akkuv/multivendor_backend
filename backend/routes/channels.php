<?php

use App\Enums\SupportConversationKind;
use App\Enums\SupportRequesterType;
use App\Models\SupportConversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Support Chat Channels
|--------------------------------------------------------------------------
|
| One private channel per support conversation. Only the requester who opened it
| may join; admins may join any. Without this check any authenticated user could
| subscribe to someone else's conversation and read a vendor's payout questions
| or a customer's order history in real time.
|
*/
Broadcast::channel('support.conversation.{conversationId}', function ($user, int $conversationId) {
    if ($user->isSuperAdmin()) {
        return true;
    }

    $conversation = SupportConversation::query()->find($conversationId);

    if ($conversation === null) {
        return false;
    }

    return match ($conversation->requester_type) {
        SupportRequesterType::Vendor => $user->isVendor()
            && $user->vendorProfile !== null
            && (int) $conversation->vendor_id === (int) $user->vendorProfile->id,
        SupportRequesterType::Customer => match ($conversation->kind) {
            // Product queries are tri-party: the customer who opened the
            // thread and the vendor who sells the product both read and reply.
            SupportConversationKind::ProductQuery => ($user->isCustomer()
                && (int) $conversation->customer_user_id === (int) $user->id)
                || ($user->isVendor()
                    && $user->vendorProfile !== null
                    && (int) $conversation->vendor_id === (int) $user->vendorProfile->id),
            default => $user->isCustomer()
                && (int) $conversation->customer_user_id === (int) $user->id,
        },
    };
});
