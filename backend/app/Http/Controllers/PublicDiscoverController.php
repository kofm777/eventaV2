<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PHASE 4 — public marketplace (GET /api/v1/discover).
 *
 * No auth, no tenant context => the BelongsToOrganizer global scope is a NO-OP here,
 * so this query MUST gate visibility EXPLICITLY (published + marketplace + active org)
 * rather than relying on the scope. Cross-organizer listing is intentional and is kept
 * safe by these explicit where-clauses + the whitelisted toMarketplaceArray() shape.
 *
 * The existing GET /events surface is left untouched; /discover is the curated one.
 */
class PublicDiscoverController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $events = Event::query()
            ->where('is_published', true)
            // Hide 'unlisted' from the marketplace (reachable only by direct link/storefront).
            ->where('visibility', Event::VISIBILITY_MARKETPLACE)
            // Only events whose organizer is currently active (no suspended/pending orgs).
            ->whereHas('organizer', fn ($q) => $q->where('status', Organizer::STATUS_ACTIVE))
            // Attribution + active tiers, no private org fields selected.
            ->with([
                'organizer:id,name,slug',
                'ticketTypes' => fn ($q) => $q->where('is_active', true),
            ])
            ->when($search !== '', function ($q) use ($search) {
                // Same LIKE pattern as Admin/EventController: name + location.
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->orderBy('starts_at', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Map each item to the marketplace shape (public fields + organizer attribution).
        $events->getCollection()->transform(fn (Event $event) => $event->toMarketplaceArray());

        return response()->json([
            'ok' => true,
            'events' => $events,
        ]);
    }
}
