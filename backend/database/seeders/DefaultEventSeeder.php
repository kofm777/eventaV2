<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

/**
 * Guarantees the single is_default published "Default Event" exists so the system
 * always has a target event (with guest checkout enabled) even before admins create
 * their own. Legacy participants keep event_id=NULL and are treated as belonging to
 * this default event in the UI layer; no backfill is required for correctness.
 */
class DefaultEventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Event::firstOrCreate(
            ['is_default' => true],
            [
                'name' => 'Default Event',
                'slug' => 'default-event',
                'allow_guest_checkout' => true,
                'ticket_price' => 0,
                'currency' => 'TND',
                'is_published' => true,
                'is_default' => true,
            ]
        );
    }
}
