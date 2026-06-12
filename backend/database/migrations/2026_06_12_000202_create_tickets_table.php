<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tickets (Phase 2) — ONE row per issued seat (fixes H7 quantity, H5 re-buy
     * overwrite, H6 per-person flags). Each seat carries its own revocable,
     * expiring HMAC QR (ticket_code is the jti).
     *
     * Almost every FK is nullable nullOnDelete so legacy-bridge tickets and tier/
     * order deletions never cascade-destroy seat history. status is an
     * app-validated string (valid|checked_in|cancelled|refunded), NOT a DB enum.
     * Additive, guarded create.
     */
    public function up(): void
    {
        if (Schema::hasTable('tickets')) {
            return;
        }

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organizer_id')
                ->nullable()
                ->constrained('organizers')
                ->nullOnDelete();

            // event_id nullable for legacy-bridge tickets (Demo registration with no event).
            $table->foreignId('event_id')
                ->nullable()
                ->constrained('events')
                ->nullOnDelete();

            // order_id NULL for free /register tickets.
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            $table->foreignId('order_item_id')
                ->nullable()
                ->constrained('order_items')
                ->nullOnDelete();

            $table->foreignId('ticket_type_id')
                ->nullable()
                ->constrained('ticket_types')
                ->nullOnDelete();

            // Links to the existing participant row so badges/emails reuse it (H5 fix).
            $table->foreignId('participant_id')
                ->nullable()
                ->constrained('participants')
                ->nullOnDelete();

            // Per-seat attendee snapshot (NULL-tolerant, defaults copied from buyer).
            $table->string('attendee_first_name')->nullable();
            $table->string('attendee_last_name')->nullable();
            $table->string('attendee_email')->nullable();
            $table->string('attendee_company_name')->nullable();
            $table->string('attendee_phone', 30)->nullable();

            // Snapshot of ticket_type.access_tier for fast gate checks.
            $table->string('access_tier', 50)->default('fair');

            // The jti — opaque per-seat id, e.g. 'TCK-' . Str::upper(Str::random(20)).
            $table->string('ticket_code', 40)->unique();

            $table->text('qr_token')->nullable();      // signed payload.signature
            $table->longText('qr_image')->nullable();  // base64 PNG (same shape as participants.qr_image)

            // valid | checked_in | cancelled | refunded (app-validated, NOT a DB enum)
            $table->string('status', 20)->default('valid');

            $table->timestamp('checked_in_at')->nullable();
            $table->string('scanner_user')->nullable();

            // Per-seat no-login access (mirrors orders.ticket_download_token).
            $table->string('download_token', 80)->nullable()->unique();

            // QR exp; NULL = no expiry / falls back to event.ends_at.
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index('organizer_id');
            $table->index('ticket_code');
            $table->index(['event_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
