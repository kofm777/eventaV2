<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Services\EventCancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EventController extends Controller
{
    /**
     * List events (paginated) for admin.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_published') && $request->is_published !== '') {
            $query->where('is_published', filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN));
        }

        $events = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'ok' => true,
            'events' => $events,
        ]);
    }

    /**
     * Create an event.
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['slug'] = $this->uniqueSlug($data['name']);

            $event = Event::create($data);

            return response()->json([
                'ok' => true,
                'event' => $event,
                'message' => 'Event created successfully.',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create event', ['error' => $e->getMessage()]);

            return response()->json([
                'ok' => false,
                'message' => 'Error while creating event.',
            ], 500);
        }
    }

    /**
     * Show one event (admin).
     */
    public function show(int $id): JsonResponse
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'ok' => false,
                'message' => 'Event not found.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'event' => $event,
        ]);
    }

    /**
     * Update an event. Phase 5 hardening: the slug is IMMUTABLE on rename — it is
     * minted once at store() and never regenerated here, so shared /events/{slug}
     * and storefront links never 404 after a rename. UpdateEventRequest does not
     * accept 'slug', so a client cannot smuggle one in.
     */
    public function update(UpdateEventRequest $request, int $id): JsonResponse
    {
        try {
            $event = Event::find($id);

            if (!$event) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Event not found.',
                ], 404);
            }

            $data = $request->validated();

            // Slug is intentionally left untouched on update (link stability).

            $event->update($data);

            return response()->json([
                'ok' => true,
                'event' => $event->fresh(),
                'message' => 'Event updated successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update event', ['event_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'ok' => false,
                'message' => 'Error while updating event.',
            ], 500);
        }
    }

    /**
     * Delete an event (blocked if it is the default event).
     */
    public function destroy(int $id): JsonResponse
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'ok' => false,
                'message' => 'Event not found.',
            ], 404);
        }

        if ($event->is_default) {
            return response()->json([
                'ok' => false,
                'message' => 'The default event cannot be deleted.',
            ], 422);
        }

        $event->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Event deleted successfully.',
        ]);
    }

    /**
     * Cancel an event (organizer-scoped + super-admin). One-way: marks the event
     * cancelled, then cascades — refunds PAID orders, cancels PENDING orders, and voids
     * all remaining active tickets. Hides the event from /discover + storefront and
     * blocks new purchases.
     *
     * The Event lookup is AUTO-SCOPED by the Phase 0 BelongsToOrganizer global scope:
     * owner/admin can only cancel their OWN events (another org's id -> NULL -> 404);
     * super-admin's scope is a no-op so they can cancel any event. Idempotent: re-cancelling
     * an already-cancelled event is a no-op success.
     */
    public function cancel(Request $request, int $id, EventCancellationService $service): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return response()->json([
                'ok' => false,
                'message' => 'Event not found.',
            ], 404);
        }

        try {
            $event = $service->cancel(
                $event,
                $request->user('sanctum')?->id,
                $request->boolean('manual')
            );
        } catch (\Throwable $e) {
            Log::error('Failed to cancel event', [
                'event_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error while cancelling the event.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'event' => $event,
            'message' => 'Event cancelled. Orders refunded and tickets voided.',
        ]);
    }

    /**
     * Build a unique slug from a name, ignoring an optional event id (for updates).
     */
    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'event';
        }

        $slug = $base;
        $suffix = 1;

        while (Event::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
