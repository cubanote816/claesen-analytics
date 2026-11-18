<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-document, per-provider authorization to send a plan to an AI provider (KNX-48).
 *
 * No row = the document may NOT be sent to that provider. A grant names one document
 * row (one revision) and one provider; a new revision is a new document and starts
 * forbidden again. Revoking deletes the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_plan_ai_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('knx_documents')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->foreignId('granted_by_employee_id')->nullable()->constrained('knx_employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'document_id', 'provider'], 'knx_plan_ai_auth_document_provider_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_plan_ai_authorizations');
    }
};
