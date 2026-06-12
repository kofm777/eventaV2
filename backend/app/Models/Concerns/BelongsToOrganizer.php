<?php

namespace App\Models\Concerns;

use App\Models\Organizer;
use App\Tenancy\OrganizerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Row-level multi-tenancy for Event/Order/Participant/Scan.
 *
 * Adds:
 *  (a) a belongsTo(Organizer) relation;
 *  (b) a global scope 'organizer' that filters by the current OrganizerContext's
 *      organizer_id ONLY when a concrete, non-super-admin tenant context exists.
 *      For super-admin, public/guest, console/queue and seeders the scope is a
 *      no-op so nothing is filtered (backward compatible, marketplace listing,
 *      token/order_number guest lookups, and migrate --seed all keep working);
 *  (c) a creating() hook that auto-stamps organizer_id from the context when a
 *      concrete organizer is active and the attribute has not been set explicitly,
 *      so organizer-user writes are auto-tenanted while super-admin / public /
 *      service writes keep full control over the value.
 */
trait BelongsToOrganizer
{
    /**
     * Boot the trait: register the tenant global scope and the auto-stamp hook.
     */
    public static function bootBelongsToOrganizer(): void
    {
        static::addGlobalScope('organizer', function (Builder $builder) {
            $context = app(OrganizerContext::class);

            // No-op for super-admin, public/guest, console/queue and seeders.
            if (! $context->hasTenantScope()) {
                return;
            }

            $model = $builder->getModel();

            $builder->where(
                $model->qualifyColumn('organizer_id'),
                $context->organizerId()
            );
        });

        static::creating(function (Model $model) {
            // Don't override an explicitly assigned organizer_id (backfill,
            // service layer copying $event->organizer_id, super-admin writes).
            if (! is_null($model->getAttribute('organizer_id'))) {
                return;
            }

            $context = app(OrganizerContext::class);

            // Only auto-stamp when a concrete organizer context is active.
            if ($context->hasTenantScope()) {
                $model->setAttribute('organizer_id', $context->organizerId());
            }
        });
    }

    /**
     * The organizer (tenant) this row belongs to. Nullable for legacy/backfilled rows.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }
}
