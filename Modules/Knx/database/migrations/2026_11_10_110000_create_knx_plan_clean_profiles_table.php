<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The clean-plan profile of one document page (KNX-48, re-entrega).
 *
 * Kantoor reads the PDF's layers in the browser and assigns each group a role; a person
 * corrects the roles the automatic pass gets wrong, and that correction must survive a
 * reload and be shared. `roles` is an opaque `{groupId: role}` map: the server never
 * reads the PDF nor the content of a group. `revision` is the document revision stamped
 * at the last save, so a client can tell a profile made for another revision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_plan_clean_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('knx_documents')->cascadeOnDelete();
            $table->unsignedInteger('page');
            $table->json('roles');
            $table->string('revision', 20)->nullable();
            $table->string('processing_version', 32)->nullable();
            $table->foreignId('updated_by_employee_id')->nullable()->constrained('knx_employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'document_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_plan_clean_profiles');
    }
};
