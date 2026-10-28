<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Initiated = 'INITIATED';
    case Completed = 'COMPLETED';
}
