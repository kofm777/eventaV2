<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parameterized RBAC gate (alias 'role').
 *
 * Usage: ->middleware('role:owner,admin') or 'role:superadmin'.
 *
 * Rules:
 *  - super-admin ALWAYS passes (defensive: isSuperAdmin() is also true when
 *    organizer_id is NULL, so a super-admin never gets blocked here);
 *  - otherwise the admin's role must be in the allowed CSV, else 403.
 *
 * This is the AUTHORIZATION layer (which actions a role may invoke). Tenant DATA
 * isolation is already automatic via the Phase 0 BelongsToOrganizer global scope.
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $admin = $request->user('sanctum');

        if (! $admin instanceof Admin) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Super-admin bypasses every role gate.
        if ($admin->isSuperAdmin()) {
            return $next($request);
        }

        if (! in_array($admin->role, $roles, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        return $next($request);
    }
}
