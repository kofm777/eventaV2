<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PHASE 6 — create the attendees table (Eventbrite-style optional accounts).
     *
     * Attendees are a THIRD, platform-wide identity, fully separate from admins.
     * Shape mirrors the admins table MINUS organizer_id/role/api_token (attendees are
     * NOT tenant rows — no organizer_id, no global tenant scope). hasTable-guarded so a
     * re-run on an already-migrated live DB is a no-op; creating a brand-new table
     * touches no existing data.
     */
    public function up(): void
    {
        if (Schema::hasTable('attendees')) {
            return;
        }

        Schema::create('attendees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};
