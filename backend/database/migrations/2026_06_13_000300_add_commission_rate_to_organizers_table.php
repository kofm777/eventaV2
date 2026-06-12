<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 5 — ADDITIVE / NULLABLE / IDEMPOTENT. Per-organizer commission override.
     *
     *   - commission_rate : decimal(5,4) nullable fraction in [0.0000, 1.0000]
     *                       (e.g. 0.0500 = 5%). NULL = use the platform default
     *                       config('services.payments.commission_rate'), so every
     *                       existing organizer row keeps the exact Phase 3 behavior
     *                       with zero data backfill.
     *
     * hasColumn-guarded so a live re-run under `migrate --force` is a no-op.
     */
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            if (! Schema::hasColumn('organizers', 'commission_rate')) {
                $table->decimal('commission_rate', 5, 4)->nullable()->after('website_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            if (Schema::hasColumn('organizers', 'commission_rate')) {
                $table->dropColumn('commission_rate');
            }
        });
    }
};
