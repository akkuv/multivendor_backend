<?php

namespace App\Enums;

enum ShipmentPickupStatus: string
{
    case None = 'NONE';
    case Requested = 'REQUESTED';
    case Scheduled = 'SCHEDULED';
    case PickedUp = 'PICKED_UP';
    case Failed = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::None => __('Not requested'),
            self::Requested => __('Pickup requested'),
            self::Scheduled => __('Pickup scheduled'),
            self::PickedUp => __('Picked up'),
            self::Failed => __('Pickup failed'),
        };
    }
}
