<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixAccessTypeEnumInParticipantsTable extends Migration
{
    public function up()
    {
        // Use raw SQL for enum change for best compatibility
        DB::statement("ALTER TABLE participants MODIFY access_type ENUM('fair', 'fair + conference') NOT NULL;");
    }

    public function down()
    {
        // If you had previous values, revert here (example: 'fair', 'both', 'foire')
        DB::statement("ALTER TABLE participants MODIFY access_type ENUM('fair', 'both', 'foire') NOT NULL;");
    }
}