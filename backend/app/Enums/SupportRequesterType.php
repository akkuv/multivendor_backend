<?php

namespace App\Enums;

/**
 * Which side of the marketplace opened a support conversation.
 *
 * A conversation is always between one requester (a vendor or a customer) and
 * the admin team; this is what tells the two inboxes apart and which foreign
 * key on the conversation actually identifies the requester.
 */
enum SupportRequesterType: string
{
    case Vendor = 'VENDOR';
    case Customer = 'CUSTOMER';

    public function label(): string
    {
        return match ($this) {
            self::Vendor => __('Vendor'),
            self::Customer => __('Customer'),
        };
    }
}
