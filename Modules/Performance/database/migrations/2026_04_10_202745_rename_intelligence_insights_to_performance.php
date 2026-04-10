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
        Schema::rename('intelligence_project_insights', 'performance_project_insights');
        Schema::rename('intelligence_employee_insights', 'performance_employee_insights');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('performance_project_insights', 'intelligence_project_insights');
        Schema::rename('performance_employee_insights', 'intelligence_employee_insights');
    }
};
