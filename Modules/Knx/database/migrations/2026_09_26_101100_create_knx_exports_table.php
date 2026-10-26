<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generated reports (dossier PDF, ETS correction CSV, delivery report, hours).
 * POST /reports answers 202 and queues the work, so `status` is what tells the
 * office whether the file is ready; GET /reports/exports is the history the
 * front refreshes after asking for one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('knx_projects')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('queued');
            $table->string('path')->nullable();
            $table->foreignId('created_by_employee_id')->nullable()->constrained('knx_employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_exports');
    }
};
