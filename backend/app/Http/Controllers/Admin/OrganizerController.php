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
     * Phase 5 — set (or clear) an organizer's per-org commission rate.
     *
     * Validates commission_rate as a fraction in [0, 1] (e.g. 0.05 = 5%); a null value
     * clears the override so the organizer falls back to the platform default. Idempotent
     * (mirrors setStatus). NOTE: only NEW PAID orders pick up a changed rate — historical
     * orders keep their captured platform_fee/organizer_amount (capture-at-PAID semantics).
     */
    public function setCommissionRate(int $id, Request $request): JsonResponse
    {
        $organizer = Organizer::find($id);

        if (! $organizer) {
            return response()->json([
                'ok' => false,
                'message' => 'Organizer not found.',
            ], 404);
        }

        $validated = $request->validate([
            'commission_rate' => 'nullable|numeric|min:0|max:1',
        ]);

        $rate = array_key_exists('commission_rate', $validated) && $validated['commission_rate'] !== null
            ? round((float) $validated['commission_rate'], 4)
            : null;

        if ((string) $organizer->commission_rate !== (string) $rate) {
            $organizer->update(['commission_rate' => $rate]);

            Log::info('Organizer commission rate changed', [
                'organizer_id' => $organizer->id,
                'commission_rate' => $rate,
            ]);
        }

        return response()->json([
            'ok' => true,
            'organizer' => [
                'id' => $organizer->id,
                'commission_rate' => $organizer->commission_rate,
            ],
            'message' => 'Commission rate updated.',
        ]);
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
