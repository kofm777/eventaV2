<?php

// Multi-tenancy (Phase 0): identity is UNCHANGED. The single `admins` table +
// Sanctum 'api' guard remain the only auth mechanism. Tenancy is layered on via
// two additive Admin columns (organizer_id, role): organizer_id NULL + role
// 'superadmin' = platform staff (scope bypass); a non-null organizer_id scopes the
// admin to one organizer. No new guard/provider is needed here.
//
// PHASE 6 (additive only): a THIRD identity — public attendees — gets its OWN
// 'sanctum-attendee' guard bound to the new 'attendees' provider (eloquent ->
// App\Models\Attendee). The `defaults` + the existing web/api/admins entries are
// LEFT UNTOUCHED, so admin/organizer auth is byte-for-byte unchanged. Because the
// attendee guard's Sanctum instance is built with provider `attendees`, an ADMIN
// token presented on an attendee route fails Sanctum auth (hasValidProvider()
// requires $tokenable instanceof Attendee); conversely an ATTENDEE token on any
// admin route fails the api/web guard (provider `admins`). The two token types
// never cross over.
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

        // PHASE 6 (additive): attendee-only token guard. Bound to the `attendees`
        // provider so an admin token can never authenticate on an attendee route.
        'sanctum-attendee' => [
            'driver' => 'sanctum',
            'provider' => 'attendees',
        ],
    ],

    'providers' => [
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],

        // PHASE 6 (additive): platform-wide public attendees.
        'attendees' => [
            'driver' => 'eloquent',
            'model' => App\Models\Attendee::class,
        ],
    ],

    // Password reset brokers — TWO fully walled-off brokers, one per identity. Each
    // points at its OWN reset-tokens table so an admin reset token can NEVER reset an
    // attendee (and vice versa). The 'admins' broker no longer points at the MISSING
    // password_resets table. guards/providers/defaults above are LEFT UNTOUCHED.
    'passwords' => [
        'admins' => [
            'provider' => 'admins',
            'table' => 'admin_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'attendees' => [
            'provider' => 'attendees',
            'table' => 'attendee_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

];
