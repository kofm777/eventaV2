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
     *   - voided_at : audit stamp set when a ticket is voided (refunded/cancelled).
     *
     * The status column already supports 'cancelled'/'refunded' (Ticket constants), and
     * Ticket::isRevoked() already covers both, so the scanner gate needs ZERO change.
     * hasColumn-guarded; nullable so every existing row stays NULL == not voided.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('checked_in_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'voided_at')) {
                $table->dropColumn('voided_at');
            }
        });
    }
};
