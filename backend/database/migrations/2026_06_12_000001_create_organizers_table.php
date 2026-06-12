<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tenant root table. status is an app-validated plain string (active|suspended),
     * NOT a DB enum, matching the codebase convention. Guarded by hasTable so this is
     * safe to (re-)run on a populated live DB.
     */
    public function up(): void
    {
        if (Schema::hasTable('organizers')) {
            return;
        }

        Schema::create('organizers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active'); // active | suspended (app-validated)
            $table->string('contact_email')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizers');
    }
};
