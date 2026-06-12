<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendee email verification — ADDITIVE / NULLABLE / IDEMPOTENT only. Adds:
     *   - email_verification_token     : sha256 hash of the raw verify token (never plain).
     *   - email_verification_sent_at   : stamp for the 48h verify-link expiry window.
     *
     * email_verified_at ALREADY exists on attendees (created with the table), so it is
     * NOT re-added here — guarded with hasColumn anyway. Each addColumn is hasColumn-
     * guarded; both nullable so every existing row stays NULL == unverified/no token.
     */
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            if (! Schema::hasColumn('attendees', 'email_verification_token')) {
                $table->string('email_verification_token')->nullable();
            }

            if (! Schema::hasColumn('attendees', 'email_verification_sent_at')) {
                $table->timestamp('email_verification_sent_at')->nullable();
            }

            // email_verified_at already exists (created with the attendees table); guard
            // anyway so a fresh DB that somehow lacks it still gets the column.
            if (! Schema::hasColumn('attendees', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            if (Schema::hasColumn('attendees', 'email_verification_token')) {
                $table->dropColumn('email_verification_token');
            }

            if (Schema::hasColumn('attendees', 'email_verification_sent_at')) {
                $table->dropColumn('email_verification_sent_at');
            }
            // Intentionally do NOT drop email_verified_at — it predates this migration.
        });
    }
};
