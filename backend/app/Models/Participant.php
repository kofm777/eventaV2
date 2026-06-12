<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participant extends Model
{
    use BelongsToOrganizer, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organizer_id',
        'event_id',
        'first_name',
        'last_name',
        'company_name',
        'gender',
        'phone',
        'email',
        'access_type',
        'qr_token',
        'qr_payload',
        'qr_image',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'qr_payload' => 'array',
    ];

    /**
     * Get the scans for the participant.
     */
    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    /**
     * Get the event this participant belongs to (nullable for legacy participants).
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the participant's full name.
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Check if participant is accepted.
     */
    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Check if participant is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if participant is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
    /**
     * Scan checks (ensure boolean return).
     */
    public function hasScannedFair(): bool
    {
        return (bool) $this->scanned_fair;
    }

    public function hasScannedConference(): bool
    {
        return (bool) $this->scanned_conference;
    }
public function getCurrentBadgeStatusColor(): string
{
    if ($this->isRejected()) {
        return 'rejected';
    }

    if ($this->isPending()) {
        return 'pending';
    }

    // Must be accepted at this point
    $fair = $this->hasScannedFair();
    $conf = $this->hasScannedConference();

    if ($fair && $conf) {
        return 'both-scanned'; // optional: treat as green or new color
    }

    if ($fair) {
        return 'fair-scanned';
    }

    if ($conf) {
        return 'conference-scanned';
    }

    return 'accepted'; // approved but not scanned
}
public function getBadgeStatusLabel(): string
{
    if ($this->isRejected()) {
        return 'Rejected';
    }

    if ($this->isPending()) {
        return 'Pending';
    }

    // Must be accepted
    $fair = $this->hasScannedFair();
    $conf = $this->hasScannedConference();

    if ($fair && $conf) {
        return 'Fair + Conference Scanned';
    }

    if ($fair) {
        return 'Fair Scanned';
    }

    if ($conf) {
        return 'Conference Scanned';
    }

    return 'Accepted';
}
}
