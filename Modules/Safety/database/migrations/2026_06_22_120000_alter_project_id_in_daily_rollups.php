<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Backfill NULL values to 'GLOBAL' to satisfy the strict unique index we want
        DB::table('safety_adoption_daily_rollups')
            ->whereNull('project_id')
            ->update(['project_id' => 'GLOBAL']);

        // 2. Alter column to not null and add default
        Schema::table('safety_adoption_daily_rollups', function (Blueprint $table) {
            // Drop unique index first because we are changing the column it depends on
            $table->dropUnique('safety_rollups_unique');
            
            // Alter column
            $table->string('project_id')->default('GLOBAL')->nullable(false)->change();
            
            // Re-add unique constraint
            $table->unique(['date', 'metric_name', 'project_id'], 'safety_rollups_unique');
        });
    }

    public function down(): void
    {
        Schema::table('safety_adoption_daily_rollups', function (Blueprint $table) {
            $table->dropUnique('safety_rollups_unique');
            $table->string('project_id')->nullable()->change();
            $table->unique(['date', 'metric_name', 'project_id'], 'safety_rollups_unique');
        });

        // Revert 'GLOBAL' back to NULL
        DB::table('safety_adoption_daily_rollups')
            ->where('project_id', 'GLOBAL')
            ->update(['project_id' => null]);
    }
};
