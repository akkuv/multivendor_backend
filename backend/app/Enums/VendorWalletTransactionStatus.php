<?php

namespace App\Enums;

enum VendorWalletTransactionStatus: string
{
    case Pending = 'PENDING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
}
