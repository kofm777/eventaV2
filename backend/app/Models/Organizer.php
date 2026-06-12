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
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';

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
    ];

    /**
     * Whether the organizer is active (not suspended).
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
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
}
