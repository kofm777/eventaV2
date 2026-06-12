<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DATA migration — fully idempotent. Uses the raw query builder so no Eloquent
     * global scope is in play (the BelongsToOrganizer scope is inert under the query
     * builder, and there is no HTTP organizer context during migrate).
     *
     * Belt-and-suspenders: the events.visibility column already defaults to
     * 'marketplace' (migration 000001), but this whereNull update guarantees any row
     * inserted between migrations A and C in a multi-step deploy — or any row that
     * somehow carries a NULL visibility — is normalized to 'marketplace', so nothing
     * disappears from /discover. whereNull -> no-op on a fresh DB / on re-run.
     */
    public function up(): void
    {
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'visibility')) {
            DB::table('events')
                ->whereNull('visibility')
                ->update(['visibility' => 'marketplace']);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Safe NO-OP by design: never null-out visibility on rollback.
     */
    public function down(): void
    {
        // intentionally left blank
    }
};
