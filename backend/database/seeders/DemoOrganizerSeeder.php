<?php

namespace Database\Seeders;

use App\Models\Organizer;
use Illuminate\Database\Seeder;

/**
 * Idempotent: guarantees the Demo Organizer exists on fresh installs and on every
 * migrate --seed. Mirrors the 2026_06_12_000100 data migration so a freshly created
 * DB (where the data migration finds no rows to backfill) still has the tenant root
 * that /register and DefaultEventSeeder depend on. Must run BEFORE DefaultEventSeeder
 * and AdminSeeder so the organizer is available to stamp onto the default event.
 */
class DemoOrganizerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organizer::firstOrCreate(
            ['slug' => 'demo-organizer'],
            [
                'name' => 'Demo Organizer',
                'status' => Organizer::STATUS_ACTIVE,
            ]
        );
    }
}
