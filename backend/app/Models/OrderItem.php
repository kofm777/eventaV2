<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order line: a ticket_type bought N times at a captured unit_price (Phase 2,
 * the H7 fix). Tenanted via BelongsToOrganizer.
 */
class OrderItem extends Model
{
    use BelongsToOrganizer, HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'organizer_id',
        'order_id',
        'ticket_type_id',
        'event_id',
        'quantity',
        'unit_price',
        'line_total',
        'currency',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
