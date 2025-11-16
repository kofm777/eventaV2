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
        Schema::table('participants', function (Blueprint $table) {
            // Modify ENUM to add 'scanned'
            DB::statement("ALTER TABLE participants MODIFY COLUMN status ENUM('pending','accepted','rejected','scanned') DEFAULT 'pending'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            // Revert ENUM to original
            DB::statement("ALTER TABLE participants MODIFY COLUMN status ENUM('pending','accepted','rejected') DEFAULT 'pending'");
        });
    }
};

