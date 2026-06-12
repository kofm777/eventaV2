<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 4 — ADDITIVE / NULLABLE / IDEMPOTENT. Per-organizer storefront branding,
     * all nullable so existing rows stay valid (null is the valid default and the
     * storefront renders sensible fallbacks). Each column is hasColumn-guarded so live
     * re-runs under `migrate --force` are a no-op.
     *
     *   - logo_url     : storefront logo image URL
     *   - brand_color  : hex accent color (e.g. #2563eb), string(9) covers #RRGGBBAA
     *   - tagline      : short storefront tagline
     *   - website_url  : organizer's external website
     */
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            if (! Schema::hasColumn('organizers', 'logo_url')) {
                $table->string('logo_url')->nullable()->after('contact_email');
            }

            if (! Schema::hasColumn('organizers', 'brand_color')) {
                $table->string('brand_color', 9)->nullable()->after('logo_url');
            }

            if (! Schema::hasColumn('organizers', 'tagline')) {
                $table->string('tagline')->nullable()->after('brand_color');
            }

            if (! Schema::hasColumn('organizers', 'website_url')) {
                $table->string('website_url')->nullable()->after('tagline');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            foreach (['website_url', 'tagline', 'brand_color', 'logo_url'] as $column) {
                if (Schema::hasColumn('organizers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
