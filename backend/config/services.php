<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | Payment-gateway selection + per-gateway credentials. The PaymentService
    | binding (AppServiceProvider) reads `driver` to choose the implementation:
    |   'stub'   -> StubPaymentService   (demo, auto-confirms — NO real charge)
    |   'flouci' -> FlouciPaymentService (real TND gateway, server-verified)
    |
    | A payment-gateway swap only changes the PaymentService impl + its binding,
    | never the controllers or OrderService. success_link / fail_link are derived
    | from app.frontend_url (FRONTEND_URL), never separate env vars.
    |
    */

    'payments' => [
        // PAYMENT_DRIVER defaults to 'stub' so demos work day one.
        'driver' => env('PAYMENT_DRIVER', 'stub'),

        // Platform commission rate (e.g. 0.05 = 5%). Default 0 => no-op capture
        // (platform_fee=0, organizer_amount=amount_total) until the owner sets it.
        'commission_rate' => (float) env('PLATFORM_COMMISSION_RATE', 0),

        // Optional hard guard: allow the stub driver under APP_ENV=production only
        // when explicitly opted-in. Off by default.
        'allow_stub_in_production' => filter_var(
            env('ALLOW_STUB_PAYMENTS', false),
            FILTER_VALIDATE_BOOLEAN
        ),

        'flouci' => [
            'app_token' => env('FLOUCI_APP_TOKEN'),
            'app_secret' => env('FLOUCI_APP_SECRET'),
            'base_url' => env('FLOUCI_BASE_URL', 'https://developer.flouci.com/api'),
            'timeout_secs' => (int) env('FLOUCI_SESSION_TIMEOUT_SECS', 1200),
        ],
    ],

];
