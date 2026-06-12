<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guest free-ticket purchase (POST /api/v1/events/{slug}/purchase).
 *
 * Matched to PurchaseController::purchase + OrderService:
 *  - event must be published + allow_guest_checkout + not ended/cancelled.
 *  - a FREE order (server-computed amount_total == 0 from the tier price) bypasses
 *    every payment driver and issues immediately via ticketIssuedResponse(..., issued)
 *    -> HTTP 201 with {ok:true, ticket, client_action}.
 *  - one 'tickets' row is created per seat.
 */
class TicketPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_purchase_a_free_ticket(): void
    {
        $organizer = Organizer::create([
            'name' => 'Free Events Co',
            'slug' => 'free-events-co',
            'status' => Organizer::STATUS_ACTIVE,
        ]);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'name' => 'Open Fair',
            'slug' => 'open-fair',
            'allow_guest_checkout' => true,
            'ticket_price' => 0,
            'currency' => 'TND',
            'is_published' => true,
            // null ends_at -> the "event has ended" guard is skipped.
            'ends_at' => null,
        ]);

        // Free, active, on-sale tier with inventory.
        TicketType::create([
            'organizer_id' => $organizer->id,
            'event_id' => $event->id,
            'name' => 'Free Admission',
            'price' => 0,
            'currency' => 'TND',
            'quantity' => 100,
            'quantity_sold' => 0,
            'access_tier' => 'fair',
            'is_active' => true,
            'is_default' => true,
        ]);

        $response = $this->postJson('/api/v1/events/open-fair/purchase', [
            'first_name' => 'Guest',
            'last_name' => 'Buyer',
            'gender' => 'Male',
            'email' => 'guest.buyer@example.com',
            'access_type' => 'fair',
            'quantity' => 1,
        ]);

        // Free tickets issue immediately: ticketIssuedResponse() with a client_action
        // returns 201.
        $response->assertStatus(201)
            ->assertJson(['ok' => true])
            ->assertJsonStructure([
                'ok',
                'order' => ['order_number', 'status', 'amount_total', 'currency'],
                'ticket' => ['download_token', 'download_url'],
            ]);

        // Exactly one seat issued for this event.
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseHas('tickets', [
            'event_id' => $event->id,
            'organizer_id' => $organizer->id,
            'access_tier' => 'fair',
            'status' => 'valid',
        ]);
    }
}
