<?php

namespace App\Enums;

enum VendorStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Suspended = 'SUSPENDED';
    case Inactive = 'INACTIVE';

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }
}
