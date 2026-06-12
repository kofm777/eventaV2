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
     */
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            if (! Schema::hasColumn('scans', 'organizer_id')) {
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
        Schema::table('scans', function (Blueprint $table) {
            if (Schema::hasColumn('scans', 'organizer_id')) {
                $table->dropForeign(['organizer_id']);
                $table->dropIndex(['organizer_id']);
                $table->dropColumn('organizer_id');
            }
        });
    }
};
