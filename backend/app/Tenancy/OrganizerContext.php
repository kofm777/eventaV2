<?php

namespace App\Tenancy;

use App\Models\Admin;

/**
 * Request-scoped single source of truth for the active organizer.
 *
 * Bound as a container singleton (see AppServiceProvider) so the middleware that
 * populates it, the BelongsToOrganizer global scope that reads it, and the models
 * that auto-stamp from it all share ONE instance per request/process.
 *
 * Semantics:
 *  - super-admin  -> isSuperAdmin = true,  organizerId = null  (scope BYPASS: sees all)
 *  - organizer    -> isSuperAdmin = false, organizerId = <id>  (scope FILTERS to that org)
 *  - public/guest -> isSuperAdmin = false, organizerId = null  (scope NO-OP: token/uniqueness is the credential)
 *  - console/queue/seeders -> never set -> defaults (no-op), safe for migrate --seed and jobs.
 */
class OrganizerContext
{
    private ?int $organizerId = null;

    private bool $isSuperAdmin = false;

    /**
     * Populate the context from the authenticated admin.
     * Super-admins bypass the tenant scope; organizer users are scoped to their organizer_id.
     */
    public function setForAdmin(Admin $admin): void
    {
        if ($admin->isSuperAdmin()) {
            $this->isSuperAdmin = true;
            $this->organizerId = null;

            return;
        }

        $this->isSuperAdmin = false;
        $this->organizerId = $admin->organizer_id;
    }

    /**
     * Reset to the public/guest default (no tenant filtering, no bypass).
     */
    public function clear(): void
    {
        $this->organizerId = null;
        $this->isSuperAdmin = false;
    }

    /**
     * The current organizer id, or null for super-admin / public / console contexts.
     */
    public function organizerId(): ?int
    {
        return $this->organizerId;
    }

    /**
     * Whether the current actor is a platform super-admin (scope bypass).
     */
    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    /**
     * Whether a concrete tenant filter should be applied: a real organizer id is
     * present AND the actor is not a super-admin. False for super-admin/public/console.
     */
    public function hasTenantScope(): bool
    {
        return $this->organizerId !== null && ! $this->isSuperAdmin;
    }
}
