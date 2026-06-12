<?php

// Multi-tenancy (Phase 0): identity is UNCHANGED. The single `admins` table +
// Sanctum 'api' guard remain the only auth mechanism. Tenancy is layered on via
// two additive Admin columns (organizer_id, role): organizer_id NULL + role
// 'superadmin' = platform staff (scope bypass); a non-null organizer_id scopes the
// admin to one organizer. No new guard/provider is needed here.
return [

    'defaults' => [
        'guard' => 'web', // or 'sanctum' if using Sanctum
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],

        'api' => [
            'driver' => 'sanctum', // or 'token' if using Laravel tokens
            'provider' => 'admins',
        ],
    ],

    'providers' => [
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],
    ],

    'passwords' => [
        'admins' => [
            'provider' => 'admins',
            'table' => 'password_resets',
            'expire' => 60,
        ],
    ],

];
