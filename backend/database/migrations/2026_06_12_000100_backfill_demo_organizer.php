<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DATA migration — fully idempotent. Runs exactly once via the migrations table,
     * and even if forced to re-run is a no-op because every write is firstOrCreate /
     * guarded whereNull update / fixed-value set. Uses the raw query builder so no
     * Eloquent global scope is in play (the BelongsToOrganizer scope is inert under
     * the query builder, and there is no HTTP organizer context during migrate).
     *
     * Steps:
     *  (a) firstOrCreate the Demo Organizer (slug 'demo-organizer').
     *  (b) Backfill organizer_id on existing events/orders/participants/scans rows
     *      that don't have one yet (whereNull -> no-op on re-run / fresh DB).
     *  (c) Promote the env ADMIN_EMAIL admin AND any organizer_id-NULL admin to
     *      super-admin (role 'superadmin', organizer_id NULL) so no pre-existing
     *      admin gets locked into an organizer scope.
     *
     * Guards: skips any table/column not present yet (defensive on partial schemas).
     */
    public function up(): void
    {
        if (! Schema::hasTable('organizers')) {
            return;
        }

        // (a) Demo Organizer (idempotent firstOrCreate-by-slug via the query builder).
        $demo = DB::table('organizers')->where('slug', 'demo-organizer')->first();

        if (! $demo) {
            $now = now();
            $demoId = DB::table('organizers')->insertGetId([
                'name' => 'Demo Organizer',
                'slug' => 'demo-organizer',
                'status' => 'active',
                'contact_email' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $demoId = $demo->id;
        }

        // (b) Backfill business tables. whereNull -> only touches not-yet-backfilled
        //     rows, so re-running (or a fresh DB with no rows) is a no-op.
        foreach (['events', 'orders', 'participants', 'scans'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'organizer_id')) {
                DB::table($tableName)
                    ->whereNull('organizer_id')
                    ->update(['organizer_id' => $demoId]);
            }
        }

        // (c) Promote platform admins to super-admin.
        if (Schema::hasTable('admins')
            && Schema::hasColumn('admins', 'organizer_id')
            && Schema::hasColumn('admins', 'role')
        ) {
            $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');

            // The seeded env admin is the platform super-admin.
            DB::table('admins')
                ->where('email', $adminEmail)
                ->update(['organizer_id' => null, 'role' => 'superadmin']);

            // Defensive: any admin currently NOT bound to an organizer is platform-level.
            DB::table('admins')
                ->whereNull('organizer_id')
                ->update(['organizer_id' => null, 'role' => 'superadmin']);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Safe NO-OP by design: we never delete the Demo Organizer nor null-out FKs nor
     * demote admins on rollback, to avoid a destructive reversal on production.
     */
    public function down(): void
    {
        // intentionally left blank
    }
};
