<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_billing_alerts', function (Blueprint $table) {
            $table->id();

            // Deterministic dedup key built by the app before upsert.
            // Format: "{year}:{month}:{alert_type}:{project_id|''}:{invoice_id|''}"
            // NULLs become empty strings so the UNIQUE constraint can never be
            // silently bypassed (MySQL treats NULL != NULL in unique indexes).
            $table->string('dedup_key', 191)->unique('uq_dedup_key');

            $table->smallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->string('alert_type', 50);
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->enum('status', ['open', 'in_review', 'confirmed', 'dismissed', 'resolved'])
                  ->default('open');

            // Context
            $table->string('project_id', 20)->nullable();
            $table->integer('relation_id')->nullable();
            $table->string('invoice_id', 20)->nullable();

            // Amounts — semantics differ per alert_type:
            //   amount_activity_cost: costs detected in mirror_costs for the period
            //                         (missing_customer_invoice, unbilled_followup_cost)
            //   amount_estimated:     only when contract_price or reliable estimate exists
            //   amount_open:          confirmed balance (overdue_receivable, partial_payment)
            $table->decimal('amount_activity_cost', 12, 2)->nullable();
            $table->decimal('amount_estimated', 12, 2)->nullable();
            $table->decimal('amount_open', 12, 2)->nullable();

            // Evidence and action
            $table->json('evidence_json');
            $table->text('recommendation');
            $table->text('ai_analysis')->nullable();

            // Workflow
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->timestamps();

            $table->index(['period_year', 'period_month'], 'idx_period');
            $table->index('project_id', 'idx_project');
            $table->index(['status', 'alert_type'], 'idx_status_type');
            $table->index('severity', 'idx_severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_billing_alerts');
    }
};
