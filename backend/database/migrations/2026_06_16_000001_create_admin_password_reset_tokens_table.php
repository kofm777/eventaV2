<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Password reset — ADMIN broker table.
     *
     * The repo had NO password_resets table; config/auth.php pointed the admins broker
     * at a MISSING table. Admins and attendees are walled-off identities, so each gets
     * its OWN reset broker table — an admin reset token can NEVER touch an attendee and
     * vice versa. Standard Laravel password-broker schema (email PK, token, created_at).
     * hasTable-guarded so a re-run on an already-migrated live DB is a no-op; creating a
     * brand-new table touches no existing data.
     */
    public function up(): void
    {
        if (Schema::hasTable('admin_password_reset_tokens')) {
            return;
        }

        Schema::create('admin_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_password_reset_tokens');
    }
};
