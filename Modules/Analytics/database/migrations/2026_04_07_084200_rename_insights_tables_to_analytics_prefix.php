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
        if (Schema::hasTable('project_insights') && !Schema::hasTable('analytics_project_insights')) {
            Schema::rename('project_insights', 'analytics_project_insights');
        }

        if (Schema::hasTable('employee_insights') && !Schema::hasTable('analytics_employee_insights')) {
            Schema::rename('employee_insights', 'analytics_employee_insights');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('analytics_project_insights') && !Schema::hasTable('project_insights')) {
            Schema::rename('analytics_project_insights', 'project_insights');
        }

        if (Schema::hasTable('analytics_employee_insights') && !Schema::hasTable('employee_insights')) {
            Schema::rename('analytics_employee_insights', 'employee_insights');
        }
    }
};
