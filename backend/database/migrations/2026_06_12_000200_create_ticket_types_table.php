<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-event ticket tier table (Phase 2).
     *
     * Authoritative tiering that replaces the single event.ticket_price + global
     * fair/conference enum (event.ticket_price KEPT for backward read + backfill).
     * Additive, guarded create. organizer_id powers the BelongsToOrganizer scope;
     * quantity NULL = unlimited; quantity_sold is the durable issued-seat counter
     * incremented under a row lock at issuance.
     *
     * All ENUM-style columns are plain strings (app-validated), matching the
     * codebase convention (status/access_tier are NOT DB enums).
     */
    public function up(): void
    {
        if (Schema::hasTable('ticket_types')) {
            return;
        }

        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();

            // Tenant key (nullable for legacy parity), FK->organizers nullOnDelete.
            $table->foreignId('organizer_id')
                ->nullable()
                ->constrained('organizers')
                ->nullOnDelete();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->string('name'); // 'General Admission', 'Fair', 'Fair + Conference', ...
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('TND');

            $table->unsignedInteger('quantity')->nullable();          // NULL = unlimited inventory
            $table->unsignedInteger('quantity_sold')->default(0);     // issued-seat counter (H8 fix)
            $table->unsignedInteger('max_per_order')->nullable();     // NULL = no per-order cap

            $table->string('access_tier', 50)->default('fair');       // 'fair' | 'fair + conference'

            $table->timestamp('sales_start_at')->nullable();          // NULL = always on sale
            $table->timestamp('sales_end_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);            // the backfilled / free-register tier

            $table->timestamps();

            $table->index('organizer_id');
            $table->index('event_id');
            $table->index(['event_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
