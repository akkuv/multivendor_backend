<?php

namespace App\Enums;

enum WalletStatus: string
{
    case Active = 'ACTIVE';
    case Locked = 'LOCKED';
    case Suspended = 'SUSPENDED';

    public function canTransact(): bool
    {
        return $this === self::Active;
    }
}
