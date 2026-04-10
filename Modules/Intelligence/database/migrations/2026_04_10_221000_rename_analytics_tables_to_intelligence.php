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
        $tables = [
            'analytics_mirror_projects' => 'intelligence_mirror_projects',
            'analytics_mirror_employees' => 'intelligence_mirror_employees',
            'analytics_mirror_labor_types' => 'intelligence_mirror_labor_types',
            'analytics_mirror_labor' => 'intelligence_mirror_labor',
            'analytics_mirror_materials' => 'intelligence_mirror_materials',
            'analytics_mirror_costs' => 'intelligence_mirror_costs',
            'analytics_offer_simulations' => 'intelligence_offer_simulations',
            'analytics_project_insights' => 'intelligence_project_insights',
            'analytics_employee_insights' => 'intelligence_employee_insights',
        ];

        foreach ($tables as $oldName => $newName) {
            if (Schema::hasTable($oldName) && !Schema::hasTable($newName)) {
                Schema::rename($oldName, $newName);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'intelligence_mirror_projects' => 'analytics_mirror_projects',
            'intelligence_mirror_employees' => 'analytics_mirror_employees',
            'intelligence_mirror_labor_types' => 'analytics_mirror_labor_types',
            'intelligence_mirror_labor' => 'analytics_mirror_labor',
            'intelligence_mirror_materials' => 'analytics_mirror_materials',
            'intelligence_mirror_costs' => 'analytics_mirror_costs',
            'intelligence_offer_simulations' => 'analytics_offer_simulations',
            'intelligence_project_insights' => 'analytics_project_insights',
            'intelligence_employee_insights' => 'analytics_employee_insights',
        ];

        foreach ($tables as $oldName => $newName) {
            if (Schema::hasTable($oldName) && !Schema::hasTable($newName)) {
                Schema::rename($oldName, $newName);
            }
        }
    }
};
