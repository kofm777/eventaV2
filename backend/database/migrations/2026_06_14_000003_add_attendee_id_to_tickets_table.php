<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PHASE 6 — additive nullable tickets.attendee_id (FK -> attendees).
     *
     * Propagated from the order's attendee_id at issuance so the My-Tickets wallet can
     * query tickets directly. NULLABLE (nullOnDelete) so guest/free/legacy seats stay
     * valid and deleting an attendee never cascades ticket loss. hasColumn-guarded.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'attendee_id')) {
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
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'attendee_id')) {
                $table->dropForeign(['attendee_id']);
                $table->dropIndex(['attendee_id']);
                $table->dropColumn('attendee_id');
            }
        });
    }
};
