<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Refunds & cancellations — ADDITIVE / NULLABLE / IDEMPOTENT only. Adds:
     *   - status        : app-validated plain string (NOT a DB enum), matching the
     *                     visibility convention. NULLABLE with NO default so every
     *                     existing row stays NULL — intentionally treated as ACTIVE by
     *                     Event::isCancelled()/scopeNotCancelled() (no backfill needed).
     *   - cancelled_at  : audit stamp set when an event is cancelled.
     *
     * status is indexed (scopeNotCancelled filters on it). hasColumn-guarded so live
     * re-runs are a no-op; runs cleanly on the live populated DB with zero row rewrites.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'status')) {
                // Nullable, NO non-null default: existing rows stay NULL == active.
                $table->string('status', 20)->nullable()->after('visibility');
                $table->index('status');
            }

            if (! Schema::hasColumn('events', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }

            if (Schema::hasColumn('events', 'status')) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            }
        });
    }
};
