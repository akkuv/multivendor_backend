<?php

namespace App\Enums;

enum ProductApprovalStatus: string
{
    case PendingApproval = 'PENDING_APPROVAL';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
}
