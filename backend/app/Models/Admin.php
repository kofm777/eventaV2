<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Admin role constants (app-validated, NOT a DB enum).
     * superadmin = platform staff (organizer_id NULL, sees all);
     * owner/admin/staff = organizer-scoped (finer gating enforced in Phase 1).
     */
    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'organizer_id',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * The organizer (tenant) this admin belongs to. NULL for platform super-admins.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    /**
     * Whether this admin is a platform super-admin (scope bypass, sees all organizers).
     * Defensive: treat a NULL organizer_id as platform-level even if role lags behind.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN || $this->organizer_id === null;
    }

    /**
     * Whether this admin is scoped to a single organizer (non-platform user).
     */
    public function isOrganizerUser(): bool
    {
        return ! $this->isSuperAdmin();
    }
}
