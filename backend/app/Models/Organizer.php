<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant root for the platform. Every tenanted business row (event/order/
 * participant/scan) and every organizer-scoped admin belongs to one Organizer.
 *
 * Status is an app-validated plain string (active|suspended), NOT a DB enum,
 * matching the codebase convention of avoiding the fragile ALTER ENUM dance.
 */
class Organizer extends Model
{
    use HasFactory;

    /**
     * Organizer status constants (app-validated, NOT a DB enum).
     *
     * STATUS_PENDING is new in Phase 1 (self-signup awaiting super-admin approval).
     * It needs NO migration: the column is string(20) with an index('status'); the
     * DB default stays 'active', and only self-signup explicitly writes 'pending'.
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_PENDING = 'pending';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'status',
        'contact_email',
        // Phase 4 storefront branding (all nullable).
        'logo_url',
        'brand_color',
        'tagline',
        'website_url',
        // Phase 5 per-organizer commission override (nullable; null = platform default).
        'commission_rate',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'commission_rate' => 'decimal:4',
    ];

    /**
     * Whether the organizer is active (approved, not pending/suspended).
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Whether the organizer is awaiting super-admin approval.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Whether the organizer has been suspended by a super-admin.
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Events owned by this organizer.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Orders owned by this organizer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Participants owned by this organizer.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /**
     * Scans owned by this organizer.
     */
    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    /**
     * Admin users belonging to this organizer (super-admins have organizer_id NULL).
     */
    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class);
    }

    /**
     * Manual payout ledger entries recorded against this organizer (Phase 5).
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    /**
     * Phase 5 — the commission rate this organizer is actually charged.
     *
     * Prefers the organizer's own commission_rate when set; falls back to the
     * platform default config('services.payments.commission_rate'). When the column
     * is NULL the returned value is byte-for-byte identical to the Phase 3 config
     * rate, so OrderService produces the same platform_fee/organizer_amount math.
     */
    public function effectiveCommissionRate(): float
    {
        return $this->commission_rate !== null
            ? (float) $this->commission_rate
            : (float) config('services.payments.commission_rate', 0);
    }

    /**
     * Phase 5 — outstanding balance owed to this organizer:
     *   SUM(PAID orders.organizer_amount) - SUM(completed payouts.amount).
     *
     * Uses the orders() relation (explicitly organizer-keyed, and a no-op under the
     * super-admin scope-bypass) so it is correct from the platform console.
     */
    public function balance(): float
    {
        $gross = (float) $this->orders()
            ->where('status', Order::STATUS_PAID)
            ->sum('organizer_amount');

        $paidOut = (float) $this->payouts()
            ->where('status', Payout::STATUS_COMPLETED)
            ->sum('amount');

        return round($gross - $paidOut, 2);
    }

    /**
     * Public-safe storefront shape (Phase 4). Exposes ONLY the org's public identity
     * and nullable branding — never status, contact_email, or any tenant-internal field.
     * This is a NEW method (the model had none) so no existing serialization changes.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logo_url,
            'brand_color' => $this->brand_color,
            'tagline' => $this->tagline,
            'website_url' => $this->website_url,
        ];
    }
}
