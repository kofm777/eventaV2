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
        'paid_at',
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
        'paid_at' => 'datetime',
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
}
