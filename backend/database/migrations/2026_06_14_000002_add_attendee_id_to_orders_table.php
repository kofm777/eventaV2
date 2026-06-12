<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PHASE 6 — additive nullable orders.attendee_id (FK -> attendees).
     *
     * NULLABLE FK (nullOnDelete) so every existing/guest order stays valid and deleting
     * an attendee never cascade-deletes their orders. DEFAULTS NULL => guest checkout is
     * byte-for-byte unchanged; the column is stamped ONLY when a logged-in attendee buys.
     * hasColumn-guarded — pattern copied verbatim from add_organizer_id_to_orders_table.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'attendee_id')) {
                $table->foreignId('attendee_id')
                    ->nullable()
                    ->after('participant_id')
                    ->constrained('attendees')
                    ->nullOnDelete();

                $table->index('attendee_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'attendee_id')) {
                $table->dropForeign(['attendee_id']);
                $table->dropIndex(['attendee_id']);
                $table->dropColumn('attendee_id');
            }
        });
    }
};
