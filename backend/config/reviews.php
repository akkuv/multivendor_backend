<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Review Settings
    |--------------------------------------------------------------------------
    |
    | allow_updates:
    |   When true customers may update an existing review for a product they
    |   previously reviewed. When false a duplicate review is rejected (409).
    |   Toggling this satisfies the "prevent duplicate reviews unless admin
    |   allows updates" requirement without a dedicated settings table/CRUD.
    |
    | auto_approve:
    |   When true new customer reviews are immediately visible on the
    |   storefront. When false they sit pending admin approval.
    |
    */

    'allow_updates' => (bool) env('REVIEWS_ALLOW_UPDATES', false),

    'auto_approve' => (bool) env('REVIEWS_AUTO_APPROVE', false),

    'max_images' => (int) env('REVIEWS_MAX_IMAGES', 5),
];
