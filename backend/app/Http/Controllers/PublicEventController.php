<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\JsonResponse;

class PublicEventController extends Controller
{
    /**
     * Public listing of PUBLISHED events.
     */
    public function index(): JsonResponse
    {
        $events = Event::published()
            // Refunds & cancellations: hide cancelled events (no-op for NULL/active rows).
            ->notCancelled()
            // Eager-load active tiers so toPublicArray() exposes them read-only without N+1.
            ->with(['ticketTypes' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('starts_at', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Map each item to the public-safe shape (omits is_default).
        $events->getCollection()->transform(fn (Event $event) => $event->toPublicArray());

        return response()->json([
            'ok' => true,
            'events' => $events,
        ]);
    }

    /**
     * Public show of one published event by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $event = Event::published()
            // Refunds & cancellations: a cancelled event drops from the public surface
            // (404). Direct-link buyers are still hard-blocked at PurchaseController.
            ->notCancelled()
            ->with(['ticketTypes' => fn ($q) => $q->where('is_active', true)])
            ->where('slug', $slug)
            ->first();

        if (!$event) {
            return response()->json([
                'ok' => false,
                'message' => 'Event not found.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'event' => $event->toPublicArray(),
        ]);
    }
}
