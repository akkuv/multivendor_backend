<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Commission Configuration
    |--------------------------------------------------------------------------
    |
    | Default commission rates applied when a vendor has not set their own
    | commission rate or a matching CommissionSetting is not found.
    |
    */

    'default_rate' => env('COMMISSION_DEFAULT_RATE', 10),
    'currency' => env('COMMISSION_CURRENCY', 'INR'),

    /*
    |--------------------------------------------------------------------------
    | Settlement
    |--------------------------------------------------------------------------
    |
    | How long a delivered vendor sub-order must wait after delivery before its
    | commission is finalized and the proceeds become available in the vendor
    | wallet. This is the "return window".
    |
    */

    'settlement_return_window_days' => env('SETTLEMENT_RETURN_WINDOW_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Payouts
    |--------------------------------------------------------------------------
    |
    | Minimum and maximum amounts a vendor can request in a single payout and
    | the accepted payout methods.
    |
    */

    'min_payout_amount' => env('VENDOR_MIN_PAYOUT_AMOUNT', 100),
    'max_payout_amount' => env('VENDOR_MAX_PAYOUT_AMOUNT') ?: null,
    'payout_methods' => ['bank_transfer', 'upi'],

];
