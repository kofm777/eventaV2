<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NULLABLE FK (nullOnDelete) so legacy rows stay valid. hasColumn-guarded.
     * NOTE: participants.email stays globally UNIQUE for Phase 0 (unchanged);
     * per-organizer email uniqueness is deferred to Phase 1+ (out of scope here).
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            if (! Schema::hasColumn('participants', 'organizer_id')) {
                $table->foreignId('organizer_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizers')
                    ->nullOnDelete();

                $table->index('organizer_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            if (Schema::hasColumn('participants', 'organizer_id')) {
                $table->dropForeign(['organizer_id']);
                $table->dropIndex(['organizer_id']);
                $table->dropColumn('organizer_id');
            }
        });
    }
};
