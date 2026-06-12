<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ADDITIVE + nullable/defaulted so every existing admin row stays valid:
     *  - organizer_id NULLABLE FK (nullOnDelete) -> NULL means platform/super-admin.
     *  - role string default 'owner' (superadmin|owner|admin|staff, app-validated).
     * The seeded ADMIN row is promoted to superadmin/organizer_id NULL by the
     * 2026_06_12_000100 data migration. hasColumn-guarded for safe re-runs.
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (! Schema::hasColumn('admins', 'organizer_id')) {
                $table->foreignId('organizer_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizers')
                    ->nullOnDelete();

                $table->index('organizer_id');
            }

            if (! Schema::hasColumn('admins', 'role')) {
                $table->string('role', 20)->default('owner')->after('organizer_id');

                $table->index('role');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (Schema::hasColumn('admins', 'organizer_id')) {
                $table->dropForeign(['organizer_id']);
                $table->dropIndex(['organizer_id']);
                $table->dropColumn('organizer_id');
            }

            if (Schema::hasColumn('admins', 'role')) {
                $table->dropIndex(['role']);
                $table->dropColumn('role');
            }
        });
    }
};
