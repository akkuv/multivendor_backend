<?php

namespace App\Enums;

enum InventoryTransactionType: string
{
    case Add = 'ADD';
    case Reserve = 'RESERVE';
    case Release = 'RELEASE';
    case Sold = 'SOLD';
    case Return = 'RETURN';
    case Damaged = 'DAMAGED';
}
