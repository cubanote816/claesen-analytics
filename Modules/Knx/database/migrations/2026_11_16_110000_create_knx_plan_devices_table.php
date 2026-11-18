<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The KNX devices of one plan page (KNX-48, plano IA v2, provisional contract §4): the
 * independent device layer (BORD, pushbuttons, 180/360 detectors) with its review state.
 * Position and bbox are page fractions (0..1, top-left origin) and `symbol_id` points to the
 * ORIGINAL symbol of the PDF, so Kantoor draws the device from the source vectors. `PUT`
 * replaces one page's set; `provisional_id` (`D1`..`Dn`) is unique per document and page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_plan_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('knx_documents')->cascadeOnDelete();
            $table->unsignedInteger('page');
            $table->string('document_revision', 20)->nullable();
            $table->string('provisional_id', 16);
            $table->string('type', 16);
            $table->double('nx');
            $table->double('ny');
            $table->json('bbox');
            $table->double('rotation_deg')->nullable();
            $table->string('symbol_id', 32);
            $table->string('label', 255)->nullable();
            $table->string('status', 16)->default('proposed');
            $table->json('doubts');
            $table->foreignId('analysis_id')->nullable()->constrained('knx_plan_analyses')->nullOnDelete();
            $table->foreignId('updated_by_employee_id')->nullable()->constrained('knx_employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'page', 'provisional_id'], 'knx_plan_devices_doc_page_prov_unique');
            $table->index(['organization_id', 'document_id', 'page'], 'knx_plan_devices_org_doc_page_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_plan_devices');
    }
};
