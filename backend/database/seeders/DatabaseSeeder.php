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
    }
}
