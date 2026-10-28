<?php

namespace App\Enums;

enum WalletHoldStatus: string
{
    case Pending = 'PENDING';
    case Captured = 'CAPTURED';
    case Released = 'RELEASED';
    case Expired = 'EXPIRED';
    case Failed = 'FAILED';
}
