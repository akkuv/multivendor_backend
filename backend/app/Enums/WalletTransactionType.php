<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case Recharge = 'RECHARGE';
    case OrderPayment = 'ORDER_PAYMENT';
    case Credit = 'CREDIT';
    case Debit = 'DEBIT';
    case Refund = 'REFUND';
    case Adjustment = 'ADJUSTMENT';
    case Commission = 'COMMISSION';
    case Withdrawal = 'WITHDRAWAL';
    case OrderPayout = 'ORDER_PAYOUT';

    public function isCredit(): bool
    {
        return in_array($this, [
            self::Recharge,
            self::Refund,
            self::Adjustment,
            self::Commission,
            self::OrderPayout,
        ], true);
    }

    public function isDebit(): bool
    {
        return in_array($this, [self::OrderPayment, self::Debit, self::Withdrawal], true);
    }
}
