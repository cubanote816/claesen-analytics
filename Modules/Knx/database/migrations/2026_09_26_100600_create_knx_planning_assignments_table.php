<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One assignment per technician per day — PUT /planning is documented as
 * idempotent on (technicianId, date), so the unique index below is what makes
 * that true at the database level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_planning_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('knx_employees')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['organization_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_planning_assignments');
    }
};
