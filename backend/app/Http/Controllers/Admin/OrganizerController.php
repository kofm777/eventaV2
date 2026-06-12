<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SUPER-ADMIN ONLY platform organizers console.
 *
 * Gated by role:superadmin at the route (NOT by organizer.active). Organizer has no
 * BelongsToOrganizer trait, so these queries are naturally cross-tenant — the
 * super-admin sees every organizer regardless of the request-scoped context.
 *
 * approve/suspend/reactivate are idempotent: setting status to a value it already
 * holds is a harmless no-op that still returns ok:true.
 */
class OrganizerController extends Controller
{
    /**
     * List organizers with optional ?status= filter and ?search= (name/slug/email).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Organizer::query()->withCount('events');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        $organizers = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'ok' => true,
            'organizers' => $organizers,
        ]);
    }

    /**
     * Approve a pending organizer -> status=active (unlocks its console).
     * Also the handler for reactivate (a suspended org returning to active).
     */
    public function approve(int $id): JsonResponse
    {
        return $this->setStatus($id, Organizer::STATUS_ACTIVE, 'Organizer approved.');
    }

    /**
     * Reactivate a suspended organizer -> status=active. Same effect as approve.
     */
    public function reactivate(int $id): JsonResponse
    {
        return $this->setStatus($id, Organizer::STATUS_ACTIVE, 'Organizer reactivated.');
    }

    /**
     * Suspend an organizer -> status=suspended (blocks its users from management).
     */
    public function suspend(int $id): JsonResponse
    {
        return $this->setStatus($id, Organizer::STATUS_SUSPENDED, 'Organizer suspended.');
    }

    /**
     * Set an organizer's status idempotently and return its summary.
     */
    private function setStatus(int $id, string $status, string $message): JsonResponse
    {
        $organizer = Organizer::find($id);

        if (! $organizer) {
            return response()->json([
                'ok' => false,
                'message' => 'Organizer not found.',
            ], 404);
        }

        if ($organizer->status !== $status) {
            $organizer->update(['status' => $status]);

            Log::info('Organizer status changed', [
                'organizer_id' => $organizer->id,
                'status' => $status,
            ]);
        }

        return response()->json([
            'ok' => true,
            'organizer' => $organizer->only(['id', 'status']),
            'message' => $message,
        ]);
    }
}
