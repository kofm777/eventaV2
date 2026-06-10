<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * All ENUM-style columns use string columns with app-level validation to stay
     * portable (status / gender / access_type are validated in the application layer,
     * NOT as DB enums, to avoid the fragile ALTER TABLE MODIFY ENUM dance).
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // human-facing ref, e.g. 'ORD-XXXXXXXXXX'

            // An order ALWAYS belongs to a real event (guest purchase requires a published event).
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            // Set once the ticket/participant is issued (on PAID).
            $table->foreignId('participant_id')->nullable()->constrained('participants')->nullOnDelete();

            // Buyer details (duplicated from participant for pre-participant lookups & receipts).
            $table->string('buyer_email');
            $table->string('buyer_first_name');
            $table->string('buyer_last_name');
            $table->string('buyer_company_name')->nullable();
            $table->string('buyer_phone', 30)->nullable();
            $table->string('gender', 10)->default('other');       // app-validated
            $table->string('access_type', 50)->default('fair');   // 'fair' | 'fair + conference'

            $table->unsignedInteger('quantity')->default(1);      // v1 = always 1
            $table->decimal('amount_total', 10, 2)->default(0);   // event.ticket_price * quantity
            $table->string('currency', 3)->default('TND');

            // PENDING_PAYMENT | PAID | FAILED | CANCELLED (app-validated, NOT a DB enum)
            $table->string('status', 30)->default('PENDING_PAYMENT');

            $table->string('payment_provider', 50)->default('stub'); // 'stub' now; 'stripe'/'paymee' later
            $table->string('payment_intent_id')->nullable();         // gateway intent/session id
            $table->string('payment_reference')->nullable();         // gateway transaction id at confirmation
            $table->timestamp('paid_at')->nullable();

            // Secure random, emailed to guest; powers no-login PDF download.
            $table->string('ticket_download_token', 80)->nullable()->unique();

            $table->timestamps();

            $table->index('status');
            $table->index('buyer_email');
            $table->index('event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
