<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\TicketType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Provisions a known, deterministic set of TEST accounts — one of every identity type —
 * plus a ready-to-test published event with a free + a paid ticket tier, so the live
 * system can be exercised end-to-end (super-admin is seeded separately by AdminSeeder).
 *
 * GATED: only runs when env('SEED_TEST_ACCOUNTS') is truthy, so production is never
 * polluted unless explicitly asked. Fully idempotent (updateOrCreate keyed on email/slug),
 * so it is safe on every migrate --seed at container start. All accounts share one
 * password (env TEST_ACCOUNTS_PASSWORD, default 'DemoPass!23').
 */
class TestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (! env('SEED_TEST_ACCOUNTS')) {
            return;
        }

        $password = env('TEST_ACCOUNTS_PASSWORD', 'DemoPass!23');

        // 1) An ACTIVE organizer (tenant) the owner/admin/staff belong to.
        $organizer = Organizer::updateOrCreate(
            ['slug' => 'demo-events-co'],
            [
                'name' => 'Demo Events Co',
                'status' => Organizer::STATUS_ACTIVE,
            ]
        );

        // 2) Organizer-scoped admins: owner, manager(admin), and scanner(staff).
        //    Admin model casts password => 'hashed', so plaintext is hashed on save.
        $orgAdmins = [
            ['email' => 'owner@demo.test',   'name' => 'Demo Owner',   'role' => Admin::ROLE_OWNER],
            ['email' => 'manager@demo.test', 'name' => 'Demo Manager', 'role' => Admin::ROLE_ADMIN],
            ['email' => 'scanner@demo.test', 'name' => 'Demo Staff',   'role' => Admin::ROLE_STAFF],
        ];
        foreach ($orgAdmins as $a) {
            Admin::updateOrCreate(
                ['email' => $a['email']],
                [
                    'name' => $a['name'],
                    'password' => $password,
                    'organizer_id' => $organizer->id,
                    'role' => $a['role'],
                ]
            );
        }

        // 3) A public attendee (Attendee model also casts password => 'hashed').
        Attendee::updateOrCreate(
            ['email' => 'attendee@demo.test'],
            [
                'name' => 'Demo Attendee',
                'password' => $password,
                'phone' => '+21620000000',
                'email_verified_at' => now(),
            ]
        );

        // 4) A published, marketplace-visible event so discover/checkout/ticket/scan
        //    can be tested immediately. organizer_id set explicitly (no tenant context
        //    in a seeder, so the global scope does not auto-stamp it).
        $event = Event::updateOrCreate(
            ['slug' => 'aurora-launch-night'],
            [
                'organizer_id' => $organizer->id,
                'name' => 'Aurora Launch Night',
                'description' => 'A demo event to test the platform end-to-end — free and paid tickets, check-in, and the full attendee journey.',
                'location' => 'Tunis, Tunisia',
                'starts_at' => now()->addDays(14)->setTime(19, 0),
                'ends_at' => now()->addDays(14)->setTime(23, 30),
                'allow_guest_checkout' => true,
                'ticket_price' => 0,
                'currency' => 'TND',
                'capacity' => 500,
                'is_published' => true,
                'visibility' => 'marketplace',
                'status' => null,
                'cancelled_at' => null,
            ]
        );

        // 5) Two ticket tiers: a FREE General Admission + a PAID VIP (fair+conference).
        TicketType::updateOrCreate(
            ['event_id' => $event->id, 'name' => 'General Admission'],
            [
                'organizer_id' => $organizer->id,
                'price' => 0,
                'currency' => 'TND',
                'quantity' => 300,
                'quantity_sold' => 0,
                'access_tier' => 'fair',
                'is_active' => true,
                'is_default' => true,
            ]
        );
        TicketType::updateOrCreate(
            ['event_id' => $event->id, 'name' => 'VIP Pass'],
            [
                'organizer_id' => $organizer->id,
                'price' => 40,
                'currency' => 'TND',
                'quantity' => 100,
                'quantity_sold' => 0,
                'access_tier' => 'fair + conference',
                'is_active' => true,
                'is_default' => false,
            ]
        );

        Log::info('TestAccountsSeeder: seeded demo accounts + event', [
            'organizer' => $organizer->slug,
            'event' => $event->slug,
        ]);
    }
}
