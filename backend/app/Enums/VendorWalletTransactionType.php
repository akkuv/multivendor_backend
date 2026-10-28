<?php

namespace App\Enums;

enum VendorWalletTransactionType: string
{
    case Commission = 'COMMISSION';
    case CommissionReversal = 'COMMISSION_REVERSAL';
    case PayoutHold = 'PAYOUT_HOLD';
    case PayoutRelease = 'PAYOUT_RELEASE';
    case PayoutPaid = 'PAYOUT_PAID';
    case Adjustment = 'ADJUSTMENT';

    public function isCredit(): bool
    {
        return in_array($this, [self::Commission, self::PayoutRelease, self::Adjustment], true);
    }

    public function isDebit(): bool
    {
        return in_array($this, [self::CommissionReversal, self::PayoutHold, self::PayoutPaid], true);
    }
}
