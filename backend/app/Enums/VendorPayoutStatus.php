<?php

namespace App\Enums;

enum VendorPayoutStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Paid = 'PAID';
    case Rejected = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Approved => __('Approved'),
            self::Paid => __('Paid'),
            self::Rejected => __('Rejected'),
        };
    }

    /**
     * A payout request can only be actioned from the pending state.
     */
    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Pending => in_array($to, [self::Approved, self::Rejected, self::Paid], true),
            self::Approved => $to === self::Paid,
            default => false,
        };
    }
}
