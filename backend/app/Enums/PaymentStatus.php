<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'PENDING';
    case Paid = 'PAID';
    case Failed = 'FAILED';
    case CodPending = 'COD_PENDING';
    case Refunded = 'REFUNDED';
    case PartiallyRefunded = 'PARTIALLY_REFUNDED';
}
