<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 3 — ADDITIVE / NULLABLE / IDEMPOTENT only. Reuses the existing
     * payment_provider (driver name) + payment_intent_id (Flouci result.payment_id)
     * columns and only ADDS:
     *   - payment_link      : Flouci generate_payment result.link (redirect URL)
     *   - platform_fee      : captured platform commission on a PAID order (TND)
     *   - organizer_amount  : amount_total - platform_fee on a PAID order (TND)
     *
     * All hasColumn-guarded so live re-runs are safe; nullable so legacy rows + the
     * default-0 commission rate stay valid (no-op until a rate is set).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_link')) {
                // Flouci hosted-checkout redirect URL (result.link). text: links can be long.
                $table->text('payment_link')->nullable()->after('payment_reference');
            }

            if (! Schema::hasColumn('orders', 'platform_fee')) {
                $table->decimal('platform_fee', 10, 2)->nullable()->after('payment_link');
            }

            if (! Schema::hasColumn('orders', 'organizer_amount')) {
                $table->decimal('organizer_amount', 10, 2)->nullable()->after('platform_fee');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['organizer_amount', 'platform_fee', 'payment_link'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
