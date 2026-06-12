<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Http\JsonResponse;

/**
 * PHASE 4 — public per-organizer storefront (GET /api/v1/organizers/{slug}).
 *
 * No auth. Organizer has NO BelongsToOrganizer trait, so it is naturally cross-tenant
 * and querying it directly by slug is correct for a public endpoint — it exposes ONLY
 * the ONE matched org. Non-enumerable: missing AND non-active orgs return the SAME 404
 * message, so suspended/pending org state cannot be probed.
 *
 * Events are read via $organizer->events() (constrained to organizer_id = this org), so
 * the global scope (a no-op for public anyway) cannot widen the result set; only THIS
 * org's published events + active tiers + whitelisted public fields are serialized.
 */
class PublicOrganizerController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $organizer = Organizer::where('slug', $slug)
            ->where('status', Organizer::STATUS_ACTIVE)
            ->first();

        // Same 404 for missing AND non-active => no enumeration of org state.
        if (! $organizer) {
            return response()->json([
                'ok' => false,
                'message' => 'Organizer not found.',
            ], 404);
        }

        // The storefront is the org's canonical home, so it SHOWS both 'marketplace'
        // and 'unlisted' published events (only /discover hides unlisted).
        $events = $organizer->events()
            ->where('is_published', true)
            // Refunds & cancellations: hide cancelled events from the storefront too
            // (no-op for NULL/active rows).
            ->notCancelled()
            ->with(['ticketTypes' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('starts_at', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $events->getCollection()->transform(fn (Event $event) => $event->toPublicArray());

        return response()->json([
            'ok' => true,
            'organizer' => $organizer->toPublicArray(),
            'events' => $events,
        ]);
    }
}
