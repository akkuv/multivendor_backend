<?php

namespace App\Enums;

enum VendorWalletStatus: string
{
    case Active = 'ACTIVE';
    case Frozen = 'FROZEN';

    public function canTransact(): bool
    {
        return $this === self::Active;
    }
}
