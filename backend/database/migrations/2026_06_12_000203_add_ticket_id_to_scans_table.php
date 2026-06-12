<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add nullable scans.ticket_id (Phase 2) so new per-ticket scans reference the
     * issued seat while legacy participant scans keep ticket_id NULL. hasColumn-
     * guarded, additive, FK->tickets nullOnDelete.
     */
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            if (! Schema::hasColumn('scans', 'ticket_id')) {
                $table->foreignId('ticket_id')
                    ->nullable()
                    ->after('participant_id')
                    ->constrained('tickets')
                    ->nullOnDelete();

                $table->index('ticket_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            if (Schema::hasColumn('scans', 'ticket_id')) {
                $table->dropForeign(['ticket_id']);
                $table->dropIndex(['ticket_id']);
                $table->dropColumn('ticket_id');
            }
        });
    }
};
