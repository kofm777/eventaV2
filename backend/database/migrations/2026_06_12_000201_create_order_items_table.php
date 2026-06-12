<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * order_items (Phase 2) — models buying N (the H7 fix): one line per
     * ticket_type x quantity x unit_price x line_total captured at sale.
     *
     * ticket_type_id is nullable nullOnDelete so deleting a tier never destroys
     * order history. Additive, guarded create.
     */
    public function up(): void
    {
        if (Schema::hasTable('order_items')) {
            return;
        }

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organizer_id')
                ->nullable()
                ->constrained('organizers')
                ->nullOnDelete();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('ticket_type_id')
                ->nullable()
                ->constrained('ticket_types')
                ->nullOnDelete();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2)->default(0); // unit_price * quantity at sale
            $table->string('currency', 3)->default('TND');

            $table->timestamps();

            $table->index('organizer_id');
            $table->index('order_id');
            $table->index('ticket_type_id');
            $table->index('event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
