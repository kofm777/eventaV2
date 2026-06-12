<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketTypeRequest;
use App\Http\Requests\UpdateTicketTypeRequest;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admin CRUD for ticket_types nested under an event (Phase 2).
 *
 * Every query is auto-scoped by the BelongsToOrganizer global scope, so an organizer
 * user only ever sees/edits their own event's tiers (super-admin sees all). Event
 * ownership is additionally asserted by resolving the {event} via the scoped model.
 */
class TicketTypeController extends Controller
{
    /**
     * List the ticket types for an event.
     */
    public function index(int $eventId): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return response()->json(['ok' => false, 'message' => 'Event not found.'], 404);
        }

        $ticketTypes = $event->ticketTypes()
            ->orderBy('price', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'ok' => true,
            'ticket_types' => $ticketTypes,
        ]);
    }

    /**
     * Create a ticket type for an event.
     */
    public function store(StoreTicketTypeRequest $request, int $eventId): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return response()->json(['ok' => false, 'message' => 'Event not found.'], 404);
        }

        try {
            $data = $request->validated();
            $data['event_id'] = $event->id;
            // Inherit the event's tenant explicitly (auto-stamp also covers organizer users).
            $data['organizer_id'] = $event->organizer_id;
            $data['currency'] = $data['currency'] ?? $event->currency;

            $ticketType = DB::transaction(function () use ($data, $event) {
                // Only one default tier per event.
                if (!empty($data['is_default'])) {
                    $event->ticketTypes()->update(['is_default' => false]);
                }

                return TicketType::create($data);
            });

            return response()->json([
                'ok' => true,
                'ticket_type' => $ticketType,
                'message' => 'Ticket type created successfully.',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create ticket type', ['event_id' => $eventId, 'error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => 'Error while creating ticket type.'], 500);
        }
    }

    /**
     * Update a ticket type. Guards quantity from dropping below quantity_sold.
     */
    public function update(UpdateTicketTypeRequest $request, int $eventId, int $id): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return response()->json(['ok' => false, 'message' => 'Event not found.'], 404);
        }

        $ticketType = $event->ticketTypes()->whereKey($id)->first();

        if (!$ticketType) {
            return response()->json(['ok' => false, 'message' => 'Ticket type not found.'], 404);
        }

        $data = $request->validated();

        // Never let quantity drop below already-issued seats.
        if (array_key_exists('quantity', $data) && !is_null($data['quantity'])
            && (int) $data['quantity'] < (int) $ticketType->quantity_sold) {
            return response()->json([
                'ok' => false,
                'message' => "Quantity cannot be lower than already-issued seats ({$ticketType->quantity_sold}).",
            ], 422);
        }

        try {
            DB::transaction(function () use ($data, $event, $ticketType) {
                if (!empty($data['is_default'])) {
                    $event->ticketTypes()->where('id', '!=', $ticketType->id)->update(['is_default' => false]);
                }

                $ticketType->update($data);
            });

            return response()->json([
                'ok' => true,
                'ticket_type' => $ticketType->fresh(),
                'message' => 'Ticket type updated successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update ticket type', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => 'Error while updating ticket type.'], 500);
        }
    }

    /**
     * Delete a ticket type — BLOCKED if it has issued tickets (deactivate instead).
     */
    public function destroy(int $eventId, int $id): JsonResponse
    {
        $event = Event::find($eventId);

        if (!$event) {
            return response()->json(['ok' => false, 'message' => 'Event not found.'], 404);
        }

        $ticketType = $event->ticketTypes()->whereKey($id)->first();

        if (!$ticketType) {
            return response()->json(['ok' => false, 'message' => 'Ticket type not found.'], 404);
        }

        if ($ticketType->tickets()->exists() || (int) $ticketType->quantity_sold > 0) {
            // Preserve issued-ticket history: deactivate rather than destroy.
            $ticketType->update(['is_active' => false]);

            return response()->json([
                'ok' => true,
                'message' => 'Ticket type has issued tickets; it was deactivated instead of deleted.',
                'ticket_type' => $ticketType->fresh(),
            ]);
        }

        $ticketType->delete();

        return response()->json(['ok' => true, 'message' => 'Ticket type deleted successfully.']);
    }
}
