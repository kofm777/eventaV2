<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Organizer;
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
        // Owned by the Demo Organizer so the default event is correctly tenanted.
        // DemoOrganizerSeeder runs first; fall back to a lookup just in case.
        $demoOrganizerId = Organizer::where('slug', 'demo-organizer')->value('id');

        $event = Event::firstOrCreate(
            ['is_default' => true],
            [
                'organizer_id' => $demoOrganizerId,
                'name' => 'Default Event',
                'slug' => 'default-event',
                'allow_guest_checkout' => true,
                'ticket_price' => 0,
                'currency' => 'TND',
                'is_published' => true,
                'is_default' => true,
            ]
        );

        // Idempotently backfill organizer_id on an already-existing default event.
        if ($demoOrganizerId && is_null($event->organizer_id)) {
            $event->update(['organizer_id' => $demoOrganizerId]);
        }
    }
}
