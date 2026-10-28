<?php

namespace App\Enums;

enum AdminLedgerType: string
{
    case Refund = 'REFUND';
    case CommissionReversal = 'COMMISSION_REVERSAL';
    case Settlement = 'SETTLEMENT';
    case Payout = 'PAYOUT';
    case Manual = 'MANUAL';

    public function label(): string
    {
        return match ($this) {
            self::Refund => __('Refund'),
            self::CommissionReversal => __('Commission Reversal'),
            self::Settlement => __('Settlement'),
            self::Payout => __('Payout'),
            self::Manual => __('Manual Adjustment'),
        };
    }
}
