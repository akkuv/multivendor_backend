<?php

namespace App\Enums;

enum VendorOrderStatus: string
{
    case Pending = 'PENDING';
    case Confirmed = 'CONFIRMED';
    case Processing = 'PROCESSING';
    case Packed = 'PACKED';
    case ReadyToShip = 'READY_TO_SHIP';
    case Shipped = 'SHIPPED';
    case OutForDelivery = 'OUT_FOR_DELIVERY';
    case Delivered = 'DELIVERED';
    case Cancelled = 'CANCELLED';
    case ReturnRequested = 'RETURN_REQUESTED';
    case Returned = 'RETURNED';
    case Refunded = 'REFUNDED';

    /**
     * Position on the linear forward fulfilment path. Branching and terminal
     * states (cancelled/returns) return null.
     */
    public function rank(): ?int
    {
        return match ($this) {
            self::Pending => 1,
            self::Confirmed => 2,
            self::Processing => 3,
            self::Packed => 4,
            self::ReadyToShip => 5,
            self::Shipped => 6,
            self::OutForDelivery => 7,
            self::Delivered => 8,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Confirmed => __('Confirmed'),
            self::Processing => __('Processing'),
            self::Packed => __('Packed'),
            self::ReadyToShip => __('Ready to Ship'),
            self::Shipped => __('Shipped'),
            self::OutForDelivery => __('Out for Delivery'),
            self::Delivered => __('Delivered'),
            self::Cancelled => __('Cancelled'),
            self::ReturnRequested => __('Return Requested'),
            self::Returned => __('Returned'),
            self::Refunded => __('Refunded'),
        };
    }

    public function canTransitionTo(self $to): bool
    {
        if ($this === $to) {
            return false;
        }

        return match ($to) {
            self::Cancelled => $this->rank() !== null && $this->rank() <= 5,
            self::ReturnRequested => $this === self::Delivered,
            self::Returned => $this === self::ReturnRequested,
            self::Refunded => in_array($this, [self::ReturnRequested, self::Returned], true),
            default => $this->rank() !== null && $to->rank() !== null && $to->rank() > $this->rank(),
        };
    }
}
