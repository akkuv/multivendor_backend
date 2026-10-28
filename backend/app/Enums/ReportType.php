<?php

namespace App\Enums;

enum ReportType: string
{
    case Sales = 'sales';
    case Orders = 'orders';
    case Products = 'products';
    case Vendors = 'vendors';
    case Customers = 'customers';
    case Commission = 'commission';
    case Payments = 'payments';
    case Refunds = 'refunds';
    case Returns = 'returns';
    case Shipping = 'shipping';
    case Wallet = 'wallet';
    case Payouts = 'payouts';

    /** @return list<ReportType> */
    public static function forAdmin(): array
    {
        return [
            self::Sales,
            self::Orders,
            self::Products,
            self::Vendors,
            self::Customers,
            self::Commission,
            self::Payments,
            self::Refunds,
            self::Returns,
            self::Shipping,
            self::Wallet,
            self::Payouts,
        ];
    }

    /** @return list<ReportType> */
    public static function forVendor(): array
    {
        return [
            self::Sales,
            self::Orders,
            self::Products,
            self::Commission,
            self::Wallet,
            self::Payouts,
            self::Returns,
        ];
    }
}
