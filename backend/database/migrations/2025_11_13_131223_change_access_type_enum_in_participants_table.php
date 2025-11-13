<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Change column to VARCHAR to avoid truncation errors
        Schema::table('participants', function (Blueprint $table) {
            $table->string('access_type', 50)->change();
        });

        // Step 2: Fix existing data to match new ENUM
        DB::table('participants')->whereNotIn('access_type', ['fair', 'fair + conference'])
            ->update(['access_type' => 'fair']);

        // Step 3: Change column to ENUM safely
        DB::statement("ALTER TABLE participants MODIFY access_type ENUM('fair', 'fair + conference') NOT NULL;");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE participants MODIFY access_type ENUM('fair', 'both', 'foire') NOT NULL;");
    }
};
