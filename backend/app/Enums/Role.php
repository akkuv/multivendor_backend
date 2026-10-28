<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'SUPER_ADMIN';
    case Vendor = 'VENDOR';
    case Customer = 'CUSTOMER';
}
