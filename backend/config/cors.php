<?php

return [
    // `broadcasting/auth` is a web route, but the browser still calls it
    // cross-origin from the React app, so it needs CORS like the API does.
    'paths' => ['api/*', 'broadcasting/auth', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Comma-separated origins from CORS_ALLOWED_ORIGINS. `array_filter` runs
    // over the exploded list itself, not a single wrapped element.
    //
    // Fails closed: when nothing is configured, no origin is allowed. A `*`
    // fallback is deliberately avoided because it cannot be combined with
    // `supports_credentials => true` — browsers reject such a response — so an
    // unset value would silently break cross-origin auth anyway.
    'allowed_origins' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
        fn (string $origin): bool => $origin !== '',
    )),

    // Reflective fallback: any origin that is not explicitly listed is still
    // echoed back (never `*`), which keeps cross-origin auth working with
    // `supports_credentials` for LAN devices and preview deployments whose
    // origins are not in CORS_ALLOWED_ORIGINS.
    'allowed_origins_patterns' => ['#.*#'],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'X-Razorpay-Signature',
        'X-Shiprocket-Signature',
        'Accept',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'Idempotency-Key',
        // Sent by the React app so API responses use the active UI language.
        'X-Locale',
    ],

    'exposed_headers' => [
        'Content-Disposition',
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];
