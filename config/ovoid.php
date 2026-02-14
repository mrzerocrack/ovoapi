<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OVOID Sensitive Actions
    |--------------------------------------------------------------------------
    |
    | Saat false, endpoint yang bisa melakukan transaksi uang (transfer/pay)
    | diblokir dari tester web.
    |
    */
    'allow_sensitive_actions' => env('OVOID_ALLOW_SENSITIVE_ACTIONS', false),

    /*
    |--------------------------------------------------------------------------
    | OVOID Tester Route
    |--------------------------------------------------------------------------
    |
    | Pengaturan endpoint browser tester.
    |
    */
    'tester' => [
        'enabled' => env('OVOID_TESTER_ENABLED', true),
        'route_prefix' => env('OVOID_TESTER_ROUTE_PREFIX', 'ovoid'),
        'route_name_prefix' => env('OVOID_TESTER_ROUTE_NAME_PREFIX', 'ovoid.'),
    ],
];
