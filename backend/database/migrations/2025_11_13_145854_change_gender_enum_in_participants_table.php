<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Temporarily make gender a TEXT column (to allow updates freely)
        DB::statement("ALTER TABLE participants MODIFY gender VARCHAR(20) NULL");

        // Step 2: Convert existing data
        DB::statement("
            UPDATE participants
            SET gender = CASE
                WHEN gender = 'homme' THEN 'male'
                WHEN gender = 'femme' THEN 'female'
                WHEN gender = 'autre' THEN 'other'
                ELSE gender
            END
        ");

        // Step 3: Convert column back to ENUM with new values
        DB::statement("
            ALTER TABLE participants
            MODIFY gender ENUM('male', 'female', 'other') NOT NULL
        ");
    }

    public function down(): void
    {
        // Step 1: Relax type again
        DB::statement("ALTER TABLE participants MODIFY gender VARCHAR(20) NULL");

        // Step 2: Revert English values back to French
        DB::statement("
            UPDATE participants
            SET gender = CASE
                WHEN gender = 'male' THEN 'homme'
                WHEN gender = 'female' THEN 'femme'
                WHEN gender = 'other' THEN 'autre'
                ELSE gender
            END
        ");

        // Step 3: Reapply original ENUM
        DB::statement("
            ALTER TABLE participants
            MODIFY gender ENUM('homme', 'femme', 'autre') NOT NULL
        ");
    }
};
