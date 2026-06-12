<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Per-event ticket tier (Phase 2). Tenanted via BelongsToOrganizer.
 *
 * quantity NULL = unlimited inventory; quantity_sold is the durable issued-seat
 * counter (never derived at read time). access_tier mirrors today's strings
 * ('fair' | 'fair + conference').
 */
class TicketType extends Model
{
    use BelongsToOrganizer, HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'organizer_id',
        'event_id',
        'name',
        'price',
        'currency',
        'quantity',
        'quantity_sold',
        'max_per_order',
        'access_tier',
        'sales_start_at',
        'sales_end_at',
        'is_active',
        'is_default',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'quantity_sold' => 'integer',
        'max_per_order' => 'integer',
        'sales_start_at' => 'datetime',
        'sales_end_at' => 'datetime',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Remaining inventory (null = unlimited).
     */
    public function remaining(): ?int
    {
        if (is_null($this->quantity)) {
            return null;
        }

        return max(0, (int) $this->quantity - (int) $this->quantity_sold);
    }

    /**
     * Whether at least $n more seats can be issued for this tier (unlimited = always true).
     */
    public function hasInventoryFor(int $n): bool
    {
        if (is_null($this->quantity)) {
            return true;
        }

        return (int) $this->quantity_sold + $n <= (int) $this->quantity;
    }

    /**
     * Whether the tier is active and inside its sales window right now.
     */
    public function isOnSale(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->sales_start_at && $now->lt($this->sales_start_at)) {
            return false;
        }

        if ($this->sales_end_at && $now->gt($this->sales_end_at)) {
            return false;
        }

        return true;
    }

    /**
     * Public-safe read shape for the event page (read-only ticket-type display).
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'currency' => $this->currency,
            'access_tier' => $this->access_tier,
            'max_per_order' => $this->max_per_order,
            'remaining' => $this->remaining(),
            'is_active' => $this->is_active,
            'is_on_sale' => $this->isOnSale(),
        ];
    }
}
