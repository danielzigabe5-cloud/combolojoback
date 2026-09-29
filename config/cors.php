<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Supports BOTH:
    |   • Nuxt Website (localhost:3000)
    |   • Mobile App (Expo / Flutter / React Native)
    |
    | IMPORTANT: When 'supports_credentials' is true, you MUST specify
    | explicit origins (cannot use '*').
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // ─── Nuxt Website ───
        'http://localhost:3000',
        'http://127.0.0.1:3000',

        // ─── Laravel Local ───
        'http://localhost:8000',
        'http://127.0.0.1:8000',

        // ─── Mobile App (Expo / React Native) ───
        'http://localhost:19000',
        'http://localhost:19006',
        'http://127.0.0.1:19000',
        'http://127.0.0.1:19006',

        // ─── Local Network (Physical Device Testing) ───
        'http://172.16.80.82:3000',
        'http://172.16.80.82:8000',
        'http://172.16.80.82:19000',
        'http://10.45.31.220:3000',
        'http://10.45.31.220:8000',
        'http://10.45.31.220:19000',
    ],

    'allowed_origins_patterns' => [
        // Allow any local network IP for mobile testing
        '#^http://192\.168\.\d+\.\d+(:\d+)?$#',
        '#^http://10\.\d+\.\d+\.\d+(:\d+)?$#',
        '#^http://172\.(1[6-9]|2[0-9]|3[0-1])\.\d+\.\d+(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400, // 24 hours (cache preflight requests)

    'supports_credentials' => true,
];