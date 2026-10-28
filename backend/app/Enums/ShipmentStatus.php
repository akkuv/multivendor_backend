<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Pending = 'PENDING';
    case Created = 'CREATED';
    case AwbGenerated = 'AWB_GENERATED';
    case PickupRequested = 'PICKUP_REQUESTED';
    case PickupScheduled = 'PICKUP_SCHEDULED';
    case PickedUp = 'PICKED_UP';
    case InTransit = 'IN_TRANSIT';
    case OutForDelivery = 'OUT_FOR_DELIVERY';
    case Delivered = 'DELIVERED';
    case Reattempting = 'REATTEMPTING';
    case Cancelled = 'CANCELLED';
    case RtoDelivered = 'RTO_DELIVERED';
    case Failed = 'FAILED';

    /**
     * First-class milestone on the fulfilment path. Everything behind this
     * milestone is treated as "still moving" for the webhook handler.
     */
    public function isForwardableTo(self $candidate): bool
    {
        return $candidate === self::Delivered && $this !== self::Delivered;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Created => __('Created'),
            self::AwbGenerated => __('AWB Generated'),
            self::PickupRequested => __('Pickup Requested'),
            self::PickupScheduled => __('Pickup Scheduled'),
            self::PickedUp => __('Picked Up'),
            self::InTransit => __('In Transit'),
            self::OutForDelivery => __('Out for Delivery'),
            self::Delivered => __('Delivered'),
            self::Reattempting => __('Reattempting Delivery'),
            self::Cancelled => __('Cancelled'),
            self::RtoDelivered => __('Returned to Sender'),
            self::Failed => __('Failed'),
        };
    }

    public function notifyCustomer(): bool
    {
        return in_array($this, [self::PickedUp, self::InTransit, self::OutForDelivery, self::Delivered, self::Reattempting, self::RtoDelivered], true);
    }
}
