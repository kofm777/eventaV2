<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * PHASE 6 — Attendee: the THIRD, platform-wide public identity (Eventbrite-style
 * optional accounts), fully separate from Admin.
 *
 * Deliberately does NOT use the BelongsToOrganizer trait: an attendee buys across many
 * organizers, so there is no organizer_id and no global tenant scope on this model.
 * Attendee Sanctum tokens are physically indistinguishable from admin tokens in
 * personal_access_tokens EXCEPT the polymorphic tokenable_type column =
 * App\Models\Attendee — that column is the entire security pivot (the 'attendees'
 * provider's Sanctum guard requires $tokenable instanceof Attendee).
 */
class Attendee extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        // Never serialize the verification token (it is a credential, like password).
        'email_verification_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_verification_sent_at' => 'datetime',
        // Auto-hash on set (consistent with Admin); never hash manually.
        'password' => 'hashed',
    ];

    /**
     * Whether this attendee has verified their email. Note: email_verification_token is
     * deliberately NOT in $fillable, so it can never be mass-assigned.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * The orders this attendee has placed (across ALL organizers).
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'attendee_id');
    }

    /**
     * The issued tickets/seats belonging to this attendee.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'attendee_id');
    }
}
