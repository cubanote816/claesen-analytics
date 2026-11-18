<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The validated AI PROPOSAL for one plan page (KNX-48, plano IA).
 *
 * Kept: the document, page, the document revision at analysis time, the provider and
 * model, the proposal schema version, the proposal itself and the token usage. NOT kept:
 * the rendered image and the input summary (they stay in the browser). A proposal is
 * never a confirmed model: Kantoor applies it and a person confirms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_plan_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('knx_documents')->cascadeOnDelete();
            $table->unsignedInteger('page');
            $table->string('document_revision', 20)->nullable();
            $table->string('provider', 32);
            $table->string('model', 100)->nullable();
            $table->string('schema_version', 32);
            $table->json('proposal');
            $table->json('usage')->nullable();
            $table->foreignId('created_by_employee_id')->nullable()->constrained('knx_employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'document_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_plan_analyses');
    }
};
