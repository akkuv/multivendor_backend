<?php

namespace App\Enums;

enum CommissionTransactionType: string
{
    case Charge = 'CHARGE';
    case Reversal = 'REVERSAL';
}
