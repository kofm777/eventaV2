<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use BelongsToOrganizer, HasFactory;

    /**
     * Visibility constants (Phase 4, app-validated plain string, NOT a DB enum).
     *
     * VISIBILITY_MARKETPLACE : shows on /discover + storefront + direct slug link.
     * VISIBILITY_UNLISTED    : hidden from /discover, still reachable by direct slug
     *                          link and shown on the owning organizer's storefront.
     */
    public const VISIBILITY_MARKETPLACE = 'marketplace';
    public const VISIBILITY_UNLISTED = 'unlisted';

    /**
     * Lifecycle status constants (app-validated plain string, NOT a DB enum, matching
     * the visibility convention). NULL is treated as STATUS_ACTIVE for back-compat so
     * every existing row stays active/visible with no backfill.
     *
     * STATUS_ACTIVE    : normal — listed on /discover + storefront, purchasable.
     * STATUS_CANCELLED : event cancelled — hidden from /discover + storefront, purchase
     *                    blocked, all orders refunded/cancelled + tickets voided.
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organizer_id',
        'name',
        'slug',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'allow_guest_checkout',
        'ticket_price',
        'currency',
        'capacity',
        'is_published',
        'visibility',
        // Refunds & cancellations (additive, nullable). NULL == active.
        'status',
        'cancelled_at',
        'is_default',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'allow_guest_checkout' => 'boolean',
        'ticket_price' => 'decimal:2',
        'capacity' => 'integer',
        'is_published' => 'boolean',
        'is_default' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the participants for the event.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /**
     * Get the orders for the event.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the ticket types (tiers) for the event.
     */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    /**
     * Get the issued tickets (one per seat) for the event.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Scope a query to only include published events.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope a query to only include marketplace-visible events (excludes 'unlisted').
     * Used by the public /discover surface so unlisted events stay off the marketplace.
     */
    public function scopeMarketplace(Builder $query): Builder
    {
        return $query->where('visibility', self::VISIBILITY_MARKETPLACE);
    }

    /**
     * Whether this event has been cancelled. NULL status == active (back-compat), so
     * only an explicit STATUS_CANCELLED counts as cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Scope a query to exclude cancelled events. NULL/'active' rows pass through, so this
     * is a no-op for every existing (NULL-status) row — nothing currently listed drops.
     */
    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('status')
                ->orWhere('status', '<>', self::STATUS_CANCELLED);
        });
    }

    /**
     * Fields exposed to the public API (omits is_default / internal-only flags).
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'location' => $this->location,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'allow_guest_checkout' => $this->allow_guest_checkout,
            'ticket_price' => $this->ticket_price,
            'currency' => $this->currency,
            'capacity' => $this->capacity,
            'is_published' => $this->is_published,
            // Refunds & cancellations: surface lifecycle status (NULL == active) so the
            // frontend can render a "cancelled" notice on a direct-link visit. Additive.
            'status' => $this->status,
            'cancelled_at' => $this->cancelled_at,
            // Phase 2: read-only active ticket types for the public event page.
            // Existing keys above are untouched (backward compatible). Uses the
            // already-loaded relation when eager-loaded, else lazy-loads it.
            'ticket_types' => $this->relationLoaded('ticketTypes')
                ? $this->ticketTypes->where('is_active', true)->map->toPublicArray()->values()
                : $this->ticketTypes()->where('is_active', true)->get()->map->toPublicArray()->values(),
        ];
    }

    /**
     * Marketplace-card shape (Phase 4): the exact public shape PLUS the visibility flag
     * and an organizer attribution block (id/name/slug only — no private org fields).
     *
     * Backward compatible: this is a NEW method; toPublicArray() is unchanged, so the
     * frontend PublicEvent type only gains an OPTIONAL `organizer` (+ `visibility`) field.
     * Expects organizer:id,name,slug to be eager-loaded by the caller (no N+1).
     *
     * @return array<string, mixed>
     */
    public function toMarketplaceArray(): array
    {
        return array_merge($this->toPublicArray(), [
            'visibility' => $this->visibility,
            'organizer' => $this->relationLoaded('organizer') && $this->organizer
                ? [
                    'id' => $this->organizer->id,
                    'name' => $this->organizer->name,
                    'slug' => $this->organizer->slug,
                ]
                : null,
        ]);
    }
}
