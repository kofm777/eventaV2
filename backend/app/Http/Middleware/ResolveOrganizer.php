<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Tenancy\OrganizerContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Populates the request-scoped OrganizerContext from the authenticated admin.
 *
 * Appended to the 'api' middleware group AFTER SubstituteBindings so it runs on
 * every API request. It inspects the Sanctum guard explicitly (matching how the
 * controllers read $request->user('sanctum')):
 *
 *  - authenticated super-admin -> context.isSuperAdmin = true (scope BYPASS, sees all)
 *  - authenticated organizer   -> context.organizerId  = admin.organizer_id (scope FILTERS)
 *  - unauthenticated           -> cleared (public/guest: scope is a no-op tenant filter)
 *
 * Resolving the context unconditionally (auth or not) means token/order_number
 * guest flows and the cross-organizer public marketplace listing keep working.
 */
class ResolveOrganizer
{
    public function __construct(
        private OrganizerContext $context
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if ($user instanceof Admin) {
            $this->context->setForAdmin($user);
        } else {
            $this->context->clear();
        }

        return $next($request);
    }
}
