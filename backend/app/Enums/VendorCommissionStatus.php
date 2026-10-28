<?php

namespace App\Enums;

enum VendorCommissionStatus: string
{
    case Pending = 'PENDING';
    case Settled = 'SETTLED';
    case Reversed = 'REVERSED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Settled => __('Settled'),
            self::Reversed => __('Reversed'),
        };
    }
}
