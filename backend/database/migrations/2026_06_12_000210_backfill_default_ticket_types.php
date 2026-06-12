<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DATA migration — fully idempotent (same pattern as 000100_backfill_demo_organizer).
     * Uses the raw query builder so no Eloquent global scope / HTTP organizer context is
     * in play during migrate.
     *
     * For EACH existing event that has NO ticket_type yet, insert ONE default tier from
     * the event's own columns:
     *   name = ticket_price > 0 ? 'General Admission' : 'Free Admission'
     *   price = event.ticket_price, currency = event.currency,
     *   quantity = NULL (unlimited — never invent a cap), access_tier = 'fair',
     *   is_default = true, is_active = true, organizer_id = event.organizer_id,
     *   quantity_sold = 0.
     *
     * Explicitly does NOT mint tickets for existing participants (out of scope): legacy
     * participant QRs keep scanning via the scanner's legacy branch; quantity_sold stays
     * 0 for backfilled tiers and is incremented only by new issuances. Existing
     * participants/scans/orders stay byte-for-byte intact.
     *
     * The NOT-EXISTS guard makes re-run / fresh-DB a no-op.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ticket_types') || ! Schema::hasTable('events')) {
            return;
        }

        $now = now();

        // Only events that do not already have a ticket_type (idempotent guard).
        $events = DB::table('events')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('ticket_types')
                    ->whereColumn('ticket_types.event_id', 'events.id');
            })
            ->get();

        foreach ($events as $event) {
            $price = (float) ($event->ticket_price ?? 0);

            DB::table('ticket_types')->insert([
                'organizer_id' => $event->organizer_id ?? null,
                'event_id' => $event->id,
                'name' => $price > 0 ? 'General Admission' : 'Free Admission',
                'price' => $price,
                'currency' => $event->currency ?? 'TND',
                'quantity' => null,           // unlimited — never invent a cap
                'quantity_sold' => 0,
                'max_per_order' => null,
                'access_tier' => 'fair',
                'sales_start_at' => null,
                'sales_end_at' => null,
                'is_active' => true,
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Safe NO-OP by design (never drop tiers / data on rollback), consistent with 000100.
     */
    public function down(): void
    {
        // intentionally left blank
    }
};
