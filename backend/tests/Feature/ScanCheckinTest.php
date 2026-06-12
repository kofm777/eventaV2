<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Per-ticket scan check-in (ScanController, v2 QR path).
 *
 * Rather than reconstruct a signed QR by hand, we issue a real ticket through the
 * guest purchase endpoint (which signs the v2 payload with QR_HMAC_SECRET and persists
 * tickets.qr_token), then replay that exact token at the scan endpoints. The scanning
 * admin owns the ticket's organizer, so the BelongsToOrganizer scope resolves it.
 *
 * The conference-gate assertion (a 'fair'-tier ticket rejected at /scan-conference) is
 * the most valuable check here.
 */
class ScanCheckinTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Issue a real FREE 'fair'-tier ticket via the public purchase flow and return it.
     */
    private function issueFairTicket(Organizer $organizer): Ticket
    {
        $event = Event::create([
            'organizer_id' => $organizer->id,
            'name' => 'Scan Fair',
            'slug' => 'scan-fair-event',
            'allow_guest_checkout' => true,
            'ticket_price' => 0,
            'currency' => 'TND',
            'is_published' => true,
            'ends_at' => null,
        ]);

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

        $this->postJson('/api/v1/events/scan-fair-event/purchase', [
            'first_name' => 'Scan',
            'last_name' => 'Guest',
            'gender' => 'Male',
            'email' => 'scan.guest@example.com',
            'access_type' => 'fair',
            'quantity' => 1,
        ])->assertStatus(201);

        // No tenant context active in the test process here, so the global scope is a
        // no-op and the freshly issued ticket is directly retrievable.
        $ticket = Ticket::where('event_id', $event->id)->firstOrFail();

        $this->assertNotEmpty($ticket->qr_token, 'Issued ticket must carry a signed QR token.');
        $this->assertSame('fair', $ticket->access_tier);

        return $ticket;
    }

    public function test_fair_ticket_is_rejected_at_conference_gate(): void
    {
        $organizer = Organizer::create([
            'name' => 'Scan Org',
            'slug' => 'scan-org',
            'status' => Organizer::STATUS_ACTIVE,
        ]);

        $staff = Admin::create([
            'name' => 'Gate Staff',
            'email' => 'gate.staff@example.com',
            'password' => 'password123',
            'organizer_id' => $organizer->id,
            'role' => Admin::ROLE_STAFF,
        ]);

        $ticket = $this->issueFairTicket($organizer);

        Sanctum::actingAs($staff, [], 'sanctum');

        $response = $this->postJson('/api/v1/scan-conference', [
            'payload' => $ticket->qr_token,
            'scanner_user' => 'gate-1',
        ]);

        // A 'fair'-only ticket at the conference gate: 200 ok with a not-permitted message,
        // and the ticket must NOT be checked in.
        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Conference access not permitted for this ticket.',
            ]);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => 'valid',
        ]);
    }

    public function test_fair_ticket_checks_in_at_fair_gate(): void
    {
        $organizer = Organizer::create([
            'name' => 'Scan Org 2',
            'slug' => 'scan-org-2',
            'status' => Organizer::STATUS_ACTIVE,
        ]);

        $staff = Admin::create([
            'name' => 'Gate Staff 2',
            'email' => 'gate.staff2@example.com',
            'password' => 'password123',
            'organizer_id' => $organizer->id,
            'role' => Admin::ROLE_STAFF,
        ]);

        $ticket = $this->issueFairTicket($organizer);

        Sanctum::actingAs($staff, [], 'sanctum');

        $response = $this->postJson('/api/v1/scan-fair', [
            'payload' => $ticket->qr_token,
            'scanner_user' => 'gate-1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'is_already_scanned' => false,
                'scan_type' => 'fair',
            ]);

        // The seat is now checked in.
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => 'checked_in',
        ]);
    }
}
