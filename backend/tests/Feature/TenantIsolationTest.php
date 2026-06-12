<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The single most important guard: the Phase 0 BelongsToOrganizer global scope.
 *
 * Owner A (active organizer A) hitting GET /api/v1/admin/events must see ONLY
 * organizer A's events and NEVER organizer B's. The ResolveOrganizer middleware
 * populates the OrganizerContext from the Sanctum admin, and the global scope filters
 * Event::query() to that organizer_id.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_only_their_own_organizers_events(): void
    {
        // Organizer A + active owner admin + an event for A.
        $orgA = Organizer::create([
            'name' => 'Organizer A',
            'slug' => 'organizer-a',
            'status' => Organizer::STATUS_ACTIVE,
        ]);

        $ownerA = Admin::create([
            'name' => 'Owner A',
            'email' => 'owner.a@example.com',
            'password' => 'password123',
            'organizer_id' => $orgA->id,
            'role' => Admin::ROLE_OWNER,
        ]);

        $eventA = Event::create([
            'organizer_id' => $orgA->id,
            'name' => 'Alpha Conference',
            'slug' => 'alpha-conference',
            'is_published' => true,
        ]);

        // Organizer B + owner admin + an event for B.
        $orgB = Organizer::create([
            'name' => 'Organizer B',
            'slug' => 'organizer-b',
            'status' => Organizer::STATUS_ACTIVE,
        ]);

        Admin::create([
            'name' => 'Owner B',
            'email' => 'owner.b@example.com',
            'password' => 'password123',
            'organizer_id' => $orgB->id,
            'role' => Admin::ROLE_OWNER,
        ]);

        $eventB = Event::create([
            'organizer_id' => $orgB->id,
            'name' => 'Beta Expo',
            'slug' => 'beta-expo',
            'is_published' => true,
        ]);

        // Act as owner A: the global scope must filter the listing to organizer A only.
        Sanctum::actingAs($ownerA, [], 'sanctum');

        $response = $this->getJson('/api/v1/admin/events');

        $response->assertStatus(200)
            ->assertJson(['ok' => true]);

        // The /admin/events index returns a paginator under 'events.data'.
        $ids = collect($response->json('events.data'))->pluck('id')->all();

        $this->assertContains($eventA->id, $ids, 'Owner A should see their own event.');
        $this->assertNotContains($eventB->id, $ids, "Owner A must NOT see organizer B's event.");
    }
}
