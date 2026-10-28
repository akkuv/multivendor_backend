<?php

namespace App\Enums;

enum CommissionLevel: string
{
    case Global = 'GLOBAL';
    case Category = 'CATEGORY';
    case Vendor = 'VENDOR';
    case Product = 'PRODUCT';

    /**
     * Higher priority wins when multiple rules apply to the same product.
     * Product -> Vendor -> Category -> Global.
     */
    public function priority(): int
    {
        return match ($this) {
            self::Global => 1,
            self::Category => 2,
            self::Vendor => 3,
            self::Product => 4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Global => __('Global'),
            self::Category => __('Category'),
            self::Vendor => __('Vendor'),
            self::Product => __('Product'),
        };
    }
}
