<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Wallet = 'wallet';
    case Razorpay = 'razorpay';
    case Cod = 'cod';

    public function label(): string
    {
        return match ($this) {
            self::Wallet => __('Wallet'),
            self::Razorpay => __('Razorpay'),
            self::Cod => __('Cash on Delivery'),
        };
    }
}
