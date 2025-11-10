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
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->enum('gender', ['Homme', 'Femme', 'Autre']);
            $table->string('phone', 30)->nullable();
            $table->string('email')->unique();
            $table->enum('access_type', ['foire', 'conference', 'both']);
            $table->string('qr_token', 128)->nullable();
            $table->longText('qr_payload')->nullable(); // MariaDB compatibility
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index(['status']);
            $table->index(['access_type']);
            $table->index(['email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
