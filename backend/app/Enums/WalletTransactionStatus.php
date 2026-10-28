<?php

namespace App\Enums;

enum WalletTransactionStatus: string
{
    case Pending = 'PENDING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
}
