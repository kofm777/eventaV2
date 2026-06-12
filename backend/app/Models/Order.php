<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToOrganizer, HasFactory;

    /**
     * Order lifecycle status constants (app-validated, NOT a DB enum).
     */
    public const STATUS_PENDING_PAYMENT = 'PENDING_PAYMENT';
    public const STATUS_PAID = 'PAID';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_REFUNDED = 'REFUNDED';

    /**
     * Terminal statuses — once an order reaches one of these it must never be moved
     * to another status. PAID/REFUNDED are the critical "never overwrite a successful
     * order" invariants; FAILED/CANCELLED are also terminal (no further writes).
     *
     * @var array<int, string>
     */
    public const TERMINAL = [
        self::STATUS_PAID,
        self::STATUS_REFUNDED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organizer_id',
        'order_number',
        'event_id',
        'participant_id',
        // Phase 6: nullable FK to the logged-in attendee who placed this order.
        // NULL for every guest/legacy order (guest checkout unchanged).
        'attendee_id',
        'buyer_email',
        'buyer_first_name',
        'buyer_last_name',
        'buyer_company_name',
        'buyer_phone',
        'gender',
        'access_type',
        'quantity',
        'amount_total',
        'currency',
        'status',
        'payment_provider',
        'payment_intent_id',
        'payment_reference',
        'payment_link',
        'platform_fee',
        'organizer_amount',
        'paid_at',
        // Refunds & cancellations (additive, nullable). refunded_at/cancelled_at are
        // audit stamps; refund_reference holds the gateway refund id (or 'manual').
        'refunded_at',
        'cancelled_at',
        'refund_reference',
        'ticket_download_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'amount_total' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'organizer_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Public routes bind orders by their human-facing order_number, not the numeric id.
     */
    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    /**
     * Get the event this order belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the participant (ticket) issued for this order, if any. KEPT for backward
     * read: for quantity==1 it still points at the first issued ticket's participant.
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * Phase 6: the logged-in attendee who placed this order (NULL for guest orders).
     */
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    /**
     * Get the order items (tier x qty x unit_price lines) for this order.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the issued tickets (one per seat) for this order.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Whether the order has been paid.
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Whether the order has been refunded (terminal).
     */
    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    /**
     * Whether the order has been cancelled (terminal).
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Whether the order is in a terminal status (no further status writes allowed).
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    /**
     * Phase 5 state-machine guard: whether a transition to $to is legal from the
     * current status. Only a PENDING_PAYMENT order may move to PAID/FAILED/CANCELLED;
     * a PAID order may additionally move to REFUNDED/CANCELLED (refunds/cancellations);
     * REFUNDED/FAILED/CANCELLED stay frozen so a confirm/webhook can never overwrite a
     * settled order and a double-refund is impossible.
     */
    public function canTransitionTo(string $to): bool
    {
        // No-op transition to the same status is always allowed (idempotent).
        if ($this->status === $to) {
            return true;
        }

        // Only a pending order may move forward into a settled state.
        if ($this->status === self::STATUS_PENDING_PAYMENT) {
            return in_array($to, [
                self::STATUS_PAID,
                self::STATUS_FAILED,
                self::STATUS_CANCELLED,
            ], true);
        }

        // Refunds & cancellations: a PAID order may move to REFUNDED or CANCELLED.
        // These are the ONLY two new outbound edges; everything below stays frozen,
        // so REFUNDED/FAILED/CANCELLED are still terminal and a double-refund is
        // structurally impossible (REFUNDED->REFUNDED is handled by the same-status
        // no-op above, never re-firing a gateway refund).
        if ($this->status === self::STATUS_PAID) {
            return in_array($to, [
                self::STATUS_REFUNDED,
                self::STATUS_CANCELLED,
            ], true);
        }

        // Any other (terminal) status is frozen.
        return false;
    }

    /**
     * Phase 5 guarded transition: persists the new status only when the move is legal,
     * returning true on success and false (no save) on an illegal transition (logging
     * the rejection). Callers branch on the boolean to stay idempotent.
     */
    public function transitionTo(string $status): bool
    {
        if ($this->status === $status) {
            return true; // already there — idempotent no-op
        }

        if (! $this->canTransitionTo($status)) {
            \Illuminate\Support\Facades\Log::warning('Rejected illegal order transition', [
                'order_number' => $this->order_number,
                'from' => $this->status,
                'to' => $status,
            ]);

            return false;
        }

        $this->update(['status' => $status]);

        return true;
    }
}
