<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Tenant root must exist before events (stamped onto default event) and
            // admins (super-admin promotion references it conceptually).
            DemoOrganizerSeeder::class,
            DefaultEventSeeder::class,
            AdminSeeder::class,
        ]);

        // Optional, env-gated: a full set of known TEST accounts (owner/admin/staff/
        // attendee) + a demo event. Only runs when SEED_TEST_ACCOUNTS is set, so prod
        // stays clean unless explicitly requested.
        if (env('SEED_TEST_ACCOUNTS')) {
            $this->call([TestAccountsSeeder::class]);
        }
    }
}
