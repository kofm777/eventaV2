<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('allow_guest_checkout')->default(false);
            $table->decimal('ticket_price', 10, 2)->default(0);
            $table->string('currency', 3)->default('TND');
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('slug');
            $table->index('is_published');
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
