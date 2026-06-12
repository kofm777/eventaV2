<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NULLABLE FK (nullOnDelete) so legacy rows stay valid and deleting an organizer
     * never cascade-deletes business data. hasColumn-guarded for safe live re-runs.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'organizer_id')) {
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
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'organizer_id')) {
                $table->dropForeign(['organizer_id']);
                $table->dropIndex(['organizer_id']);
                $table->dropColumn('organizer_id');
            }
        });
    }
};
