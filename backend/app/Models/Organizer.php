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
