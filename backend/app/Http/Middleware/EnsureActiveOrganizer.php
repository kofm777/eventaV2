<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\Organizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Active-organizer gate (alias 'organizer.active').
 *
 * Placed on management + scan route groups. A pending/suspended organizer owner CAN
 * still log in and read /auth/me (so the SPA renders a banner), but is BLOCKED from
 * every management action here:
 *
 *  - super-admin                 -> passes (no organizer to check, scope bypass);
 *  - organizer user, status=active   -> passes;
 *  - organizer user, pending/suspended -> 403 {ok:false, status, message}.
 *
 * The token stays valid; this GATE (not the token) is the control, so suspending an
 * org blocks its users on their very next request.
 */
class EnsureActiveOrganizer
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('sanctum');

        if (! $admin instanceof Admin) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Super-admin has no organizer to check — always passes.
        if ($admin->isSuperAdmin()) {
            return $next($request);
        }

        $organizer = $admin->organizer;

        if (! $organizer || ! $organizer->isActive()) {
            $status = $organizer?->status ?? Organizer::STATUS_SUSPENDED;

            $message = $status === Organizer::STATUS_PENDING
                ? 'Your organizer account is pending approval.'
                : 'Your organizer account has been suspended.';

            return response()->json([
                'ok' => false,
                'status' => $status,
                'message' => $message,
            ], 403);
        }

        return $next($request);
    }
}
