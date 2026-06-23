<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_adoption_daily_rollups', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('metric_name')->index(); // e.g., 'dau', 'mau', 'inspections_completed'
            $table->string('project_id')->nullable()->index(); // Null for global, specific ID for project-level rollups
            $table->decimal('value', 12, 2)->default(0); // Decimal to handle ratios or counts
            $table->timestamps();
            
            // Unique constraint to prevent duplicate rollups for the same day/metric/project
            $table->unique(['date', 'metric_name', 'project_id'], 'safety_rollups_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_adoption_daily_rollups');
    }
};
