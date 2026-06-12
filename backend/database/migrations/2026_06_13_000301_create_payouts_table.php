<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 5 — NEW platform-owner payout ledger. Tunisian gateways lack auto-split,
     * so payouts are MANUAL ledger entries recorded by a super-admin. An organizer's
     * BALANCE = SUM(orders.organizer_amount WHERE status=PAID) - SUM(payouts.amount
     * WHERE status=completed). This table is NOT tenant-scoped (no BelongsToOrganizer);
     * it is a platform-owner ledger queried explicitly by organizer_id.
     *
     * status is an app-validated plain string (completed|pending|reversed), NOT a DB
     * enum, matching the codebase convention. Guarded by hasTable so this is safe to
     * (re-)run on a populated live DB under `migrate --force`.
     */
    public function up(): void
    {
        if (Schema::hasTable('payouts')) {
            return;
        }

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('TND');
            $table->string('status', 20)->default('completed'); // completed | pending | reversed (app-validated)
            $table->string('period')->nullable();               // free-text label, e.g. '2026-06'
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index('organizer_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
