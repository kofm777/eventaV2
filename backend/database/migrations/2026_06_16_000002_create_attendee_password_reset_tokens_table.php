<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Password reset — ATTENDEE broker table.
     *
     * SEPARATE broker table from the admins one (admin/attendee identities are fully
     * walled off — an attendee reset token can NEVER reset an admin). Same standard
     * Laravel password-broker schema (email PK, token, created_at). hasTable-guarded so
     * a re-run is a no-op; creating a brand-new table touches no existing data.
     */
    public function up(): void
    {
        if (Schema::hasTable('attendee_password_reset_tokens')) {
            return;
        }

        Schema::create('attendee_password_reset_tokens', function (Blueprint $table) {
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
        Schema::dropIfExists('attendee_password_reset_tokens');
    }
};
