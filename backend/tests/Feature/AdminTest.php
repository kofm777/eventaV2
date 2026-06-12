<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Organizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Admin console auth/guarding (replaces the meaningless default GET '/' stub —
 * this is an API with no web '/' route).
 *
 * Asserts only the routing/guard contract that is unambiguous in routes/api.php +
 * the EnsureActiveOrganizer / auth:sanctum middleware:
 *  - the admin events route is behind auth:sanctum (401 when unauthenticated);
 *  - an active-organizer owner admin reaches it and gets {ok:true}.
 */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_events_route_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/events');

        $response->assertStatus(401);
    }

    public function test_active_organizer_owner_can_list_events(): void
    {
        $organizer = Organizer::create([
            'name' => 'Org One',
            'slug' => 'org-one',
            'status' => Organizer::STATUS_ACTIVE,
        ]);

        $admin = Admin::create([
            'name' => 'Owner One',
            'email' => 'owner.one@example.com',
            'password' => 'password123',
            'organizer_id' => $organizer->id,
            'role' => Admin::ROLE_OWNER,
        ]);

        Sanctum::actingAs($admin, [], 'sanctum');

        $response = $this->getJson('/api/v1/admin/events');

        $response->assertStatus(200)
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['ok', 'events']);
    }
}
