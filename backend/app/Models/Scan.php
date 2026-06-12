<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scan extends Model
{
    use BelongsToOrganizer, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organizer_id',
        'participant_id',
        'ticket_id',
        'scanned_at',
        'scanner_user',
        'raw_payload',
        'scan_type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scanned_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    /**
     * Get the participant that owns the scan.
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * Get the ticket (seat) this scan checked in (NULL for legacy participant scans).
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
