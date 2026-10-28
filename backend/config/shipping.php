<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shipping Rates
    |--------------------------------------------------------------------------
    |
    | The legacy flat delivery charge, kept for backwards compatibility.
    | Per-vendor charges are read from the vendor profile (shipping_charge /
    | free_shipping_threshold) via the PricingService.
    |
    */

    'flat_rate' => (float) env('SHIPPING_FLAT_RATE', 50.0),

    /*
    |--------------------------------------------------------------------------
    | Cash on Delivery
    |--------------------------------------------------------------------------
    |
    | A flat convenience fee applied to orders paid by COD. Charged once per
    | order, then distributed across vendor groups proportionally to subtotal.
    |
    */

    'cod_charge' => (float) env('COD_CHARGE', 0.0),

];
