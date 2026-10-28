<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'PENDING';
    case Confirmed = 'CONFIRMED';
    case Processing = 'PROCESSING';
    case PartiallyShipped = 'PARTIALLY_SHIPPED';
    case Shipped = 'SHIPPED';
    case PartiallyDelivered = 'PARTIALLY_DELIVERED';
    case Delivered = 'DELIVERED';
    case Cancelled = 'CANCELLED';
    case Completed = 'COMPLETED';
    case ReturnRequested = 'RETURN_REQUESTED';
    case Returned = 'RETURNED';
    case Refunded = 'REFUNDED';

    /**
     * Position on the linear forward fulfilment path. Branching and terminal
     * states (cancelled/returns) return null so the default forward rule never
     * accidentally approves an invalid jump onto them.
     */
    public function rank(): ?int
    {
        return match ($this) {
            self::Pending => 1,
            self::Confirmed => 2,
            self::Processing => 3,
            self::PartiallyShipped => 4,
            self::Shipped => 5,
            self::PartiallyDelivered => 6,
            self::Delivered => 7,
            self::Completed => 8,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Confirmed => __('Confirmed'),
            self::Processing => __('Processing'),
            self::PartiallyShipped => __('Partially Shipped'),
            self::Shipped => __('Shipped'),
            self::PartiallyDelivered => __('Partially Delivered'),
            self::Delivered => __('Delivered'),
            self::Cancelled => __('Cancelled'),
            self::Completed => __('Completed'),
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
            self::Cancelled => in_array($this, [self::Pending, self::Confirmed, self::Processing], true),
            self::ReturnRequested => in_array($this, [self::Delivered, self::Completed], true),
            self::Returned => $this === self::ReturnRequested,
            self::Refunded => in_array($this, [self::Delivered, self::Completed, self::ReturnRequested, self::Returned], true),
            default => $this->rank() !== null && $to->rank() !== null && $to->rank() > $this->rank(),
        };
    }
}
