<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily USD ledger of the OpenAI calls of plan processing.
 *
 * `knx_ai_spend_days` is the ROW LOCKED while a reservation is made (one per organization
 * and Brussels day); `knx_ai_spend_entries` holds every reservation and its settlement;
 * `knx_ai_spend_settings` the admin's override of the configured daily limit. Entries hold
 * ids, model, amounts and token counts only: never plans, room names or images.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_ai_spend_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->date('day');
            $table->decimal('limit_usd', 12, 6)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'day'], 'knx_ai_days_org_day_uq');
        });

        Schema::create('knx_ai_spend_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->date('day');
            $table->string('provider', 32);
            $table->string('operation', 32); // plan_analysis | image
            $table->string('model', 100);
            $table->string('status', 16); // reserved | settled | uncertain | released
            $table->decimal('reserved_usd', 12, 6);
            $table->decimal('actual_usd', 12, 6)->nullable();
            $table->json('usage')->nullable();
            $table->foreignId('project_id')->nullable()->constrained('knx_projects')->nullOnDelete();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedInteger('page')->nullable();
            $table->char('processing_id', 36);
            $table->string('request_id', 100)->nullable();
            $table->string('reason', 64)->nullable();
            $table->foreignId('created_by_employee_id')->nullable()->constrained('knx_employees')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'day', 'status'], 'knx_ai_entries_day_status_idx');
            $table->index('processing_id', 'knx_ai_entries_processing_idx');
        });

        Schema::create('knx_ai_spend_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('knx_ai_settings_org_uq')->constrained('organizations')->restrictOnDelete();
            $table->decimal('daily_limit_usd', 12, 6);
            $table->foreignId('updated_by_employee_id')->nullable()->constrained('knx_employees')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_ai_spend_settings');
        Schema::dropIfExists('knx_ai_spend_entries');
        Schema::dropIfExists('knx_ai_spend_days');
    }
};
