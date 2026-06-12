<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform-owner payout ledger entry (Phase 5).
 *
 * Tunisian gateways lack auto-split, so payouts are MANUAL entries recorded by a
 * super-admin. An organizer's balance is SUM(PAID orders.organizer_amount) minus
 * SUM(completed payouts.amount).
 *
 * NOT tenant-scoped: this model has NO BelongsToOrganizer trait. It is a platform-
 * owner ledger; all queries are explicit by organizer_id and only the super-admin
 * platform console reads/writes it.
 *
 * status is an app-validated plain string (completed|pending|reversed), NOT a DB enum.
 */
class Payout extends Model
{
    use HasFactory;

    /**
     * Payout status constants (app-validated, NOT a DB enum). Manual entries default
     * to completed; pending/reversed exist for future reconciliation workflows.
     */
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_REVERSED = 'reversed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organizer_id',
        'amount',
        'currency',
        'status',
        'period',
        'note',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * The organizer this payout was made to.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    /**
     * The super-admin who recorded this payout (nullable if the admin was deleted).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
