<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use BelongsToOrganizer, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organizer_id',
        'name',
        'slug',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'allow_guest_checkout',
        'ticket_price',
        'currency',
        'capacity',
        'is_published',
        'is_default',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'allow_guest_checkout' => 'boolean',
        'ticket_price' => 'decimal:2',
        'capacity' => 'integer',
        'is_published' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the participants for the event.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /**
     * Get the orders for the event.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Scope a query to only include published events.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Fields exposed to the public API (omits is_default / internal-only flags).
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'location' => $this->location,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'allow_guest_checkout' => $this->allow_guest_checkout,
            'ticket_price' => $this->ticket_price,
            'currency' => $this->currency,
            'capacity' => $this->capacity,
            'is_published' => $this->is_published,
        ];
    }
}
