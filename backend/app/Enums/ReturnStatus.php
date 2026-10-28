<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case Requested = 'REQUESTED';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case PickupScheduled = 'PICKUP_SCHEDULED';
    case PickedUp = 'PICKED_UP';
    case Received = 'RECEIVED';
    case Inspecting = 'INSPECTING';
    case ApprovedForRefund = 'APPROVED_FOR_REFUND';
    case Refunded = 'REFUNDED';
    case Closed = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Requested'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::PickupScheduled => __('Pickup Scheduled'),
            self::PickedUp => __('Picked Up'),
            self::Received => __('Received'),
            self::Inspecting => __('Inspecting'),
            self::ApprovedForRefund => __('Approved For Refund'),
            self::Refunded => __('Refunded'),
            self::Closed => __('Closed'),
        };
    }

    public function canTransitionTo(self $to): bool
    {
        if ($this === $to) {
            return false;
        }

        return match ($this) {
            self::Requested => in_array($to, [self::Approved, self::Rejected, self::Closed], true),
            self::Approved => in_array($to, [self::PickupScheduled, self::PickedUp, self::Closed], true),
            self::Rejected => $to === self::Closed,
            self::PickupScheduled => in_array($to, [self::PickedUp, self::Rejected, self::Closed], true),
            self::PickedUp => in_array($to, [self::Received, self::Closed], true),
            self::Received => in_array($to, [self::Inspecting, self::Closed], true),
            self::Inspecting => in_array($to, [self::ApprovedForRefund, self::Rejected, self::Closed], true),
            self::ApprovedForRefund => in_array($to, [self::Refunded, self::Closed], true),
            self::Refunded => $to === self::Closed,
            self::Closed => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Refunded, self::Closed], true);
    }
}
