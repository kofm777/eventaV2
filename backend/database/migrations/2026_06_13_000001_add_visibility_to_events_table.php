<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 4 — ADDITIVE / IDEMPOTENT. Adds events.visibility, an app-validated
     * plain string (NOT a DB enum), matching the codebase "no DB enum" convention.
     *
     *   'marketplace' (default) : shows on /discover + storefront + direct link.
     *   'unlisted'              : hidden from /discover, still reachable by direct
     *                             slug link and on the organizer storefront.
     *
     * NOT NULL with a default is safe because every existing row immediately receives
     * 'marketplace' from the column default (and the 000100 data migration is belt-and-
     * suspenders). hasColumn-guarded so live re-runs under `migrate --force` are a no-op.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'visibility')) {
                $table->string('visibility', 20)
                    ->default('marketplace')
                    ->after('is_published');

                $table->index('visibility');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'visibility')) {
                $table->dropIndex(['visibility']);
                $table->dropColumn('visibility');
            }
        });
    }
};
