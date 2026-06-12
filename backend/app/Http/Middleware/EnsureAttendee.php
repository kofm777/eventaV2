<?php

namespace App\Http\Middleware;

use App\Models\Attendee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHASE 6 — attendee-only gate (alias 'attendee').
 *
 * Belt-and-suspenders backstop placed AFTER `auth:sanctum-attendee`. The guard already
 * rejects a non-attendee token at the Sanctum layer (its provider is `attendees`, so
 * hasValidProvider() requires $tokenable instanceof Attendee). This middleware asserts
 * the same invariant a second, independent time so an ADMIN token can never reach an
 * attendee-only route even if guard wiring ever changed — exactly mirroring how
 * RequireRole/EnsureActiveOrganizer gate on `instanceof Admin`.
 */
class EnsureAttendee
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('sanctum-attendee') instanceof Attendee) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return $next($request);
    }
}
