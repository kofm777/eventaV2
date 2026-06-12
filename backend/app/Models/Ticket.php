<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ONE row per issued seat (Phase 2). Each ticket carries its own revocable,
 * expiring HMAC QR (ticket_code is the jti). Tenanted via BelongsToOrganizer so
 * an organizer scanning another org's ticket simply gets null (the H4 fix).
 */
class Ticket extends Model
{
    use BelongsToOrganizer, HasFactory;

    /**
     * Ticket lifecycle status constants (app-validated, NOT a DB enum).
     */
    public const STATUS_VALID = 'valid';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'organizer_id',
        'event_id',
        'order_id',
        'order_item_id',
        'ticket_type_id',
        'participant_id',
        'attendee_first_name',
        'attendee_last_name',
        'attendee_email',
        'attendee_company_name',
        'attendee_phone',
        'access_tier',
        'ticket_code',
        'qr_token',
        'qr_image',
        'status',
        'checked_in_at',
        'scanner_user',
        'download_token',
        'expires_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'checked_in_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function organizerRel(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * Whether this seat has already been checked in.
     */
    public function isCheckedIn(): bool
    {
        return $this->status === self::STATUS_CHECKED_IN;
    }

    /**
     * Whether this seat is revoked (cancelled or refunded) — fails the gate.
     */
    public function isRevoked(): bool
    {
        return in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_REFUNDED], true);
    }

    /**
     * Whether this seat's QR has expired (expires_at in the past). NULL = no expiry.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->greaterThan($this->expires_at);
    }
}
