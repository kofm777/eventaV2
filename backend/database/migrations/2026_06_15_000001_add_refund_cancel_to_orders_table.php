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
     *   - refunded_at       : audit stamp set when a PAID order is refunded.
     *   - cancelled_at      : audit stamp set when an order is cancelled.
     *   - refund_reference  : gateway refund id (or 'manual' for out-of-band refunds).
     *
     * All hasColumn-guarded so live re-runs under `migrate --force` are a no-op. Nullable
     * with no default so every existing row stays NULL == not refunded / not cancelled.
     * No new index: status (what the ledger filters on) is already indexed.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('paid_at');
            }

            if (! Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('refunded_at');
            }

            if (! Schema::hasColumn('orders', 'refund_reference')) {
                $table->string('refund_reference')->nullable()->after('payment_reference');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['refund_reference', 'cancelled_at', 'refunded_at'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
