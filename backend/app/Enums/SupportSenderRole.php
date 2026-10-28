<?php

namespace App\Enums;

enum SupportSenderRole: string
{
    case Vendor = 'VENDOR';
    case Customer = 'CUSTOMER';
    case Admin = 'SUPER_ADMIN';
    case Bot = 'BOT';

    public function label(): string
    {
        return match ($this) {
            self::Vendor => __('Vendor'),
            self::Customer => __('Customer'),
            self::Admin => __('Support Team'),
            self::Bot => __('Support Assistant'),
        };
    }

    /**
     * Staff are the admin team plus the assistant speaking for them. Requesters
     * (vendors and customers) are the other side of the conversation.
     */
    public function isStaff(): bool
    {
        return $this === self::Admin || $this === self::Bot;
    }

    /**
     * The role the requester of a conversation of this type posts under.
     */
    public static function requesterFor(SupportRequesterType $type): self
    {
        return match ($type) {
            SupportRequesterType::Vendor => self::Vendor,
            SupportRequesterType::Customer => self::Customer,
        };
    }
}
