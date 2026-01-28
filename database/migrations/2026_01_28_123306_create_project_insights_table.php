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
        Schema::create('project_insights', function (Blueprint $table) {
            $table->id();
            // Project ID from legacy DB is a string (e.g., '2024-001'), verified in PROJECT_CONTEXT.md
            // Indexed for fast lookups. Unique to enforce one insight per project.
            $table->string('project_id')->unique();

            // Insight Type enum: pre-calculation, post-mortem, audit_budget, manual-audit
            // Using string for flexibility, but enum in code.
            $table->string('insight_type')->default('audit_budget');

            // Efficiency Score 0-100
            $table->decimal('efficiency_score', 5, 2)->nullable();

            // Critical Leak (Root cause of margin loss)
            $table->string('critical_leak')->nullable();

            // AI Generated Content
            $table->text('ai_summary')->nullable();
            $table->text('golden_rule')->nullable(); // Strategic advice

            // Snapshot of project state for historical trending
            $table->json('full_dna')->nullable();

            // Semantic Caching
            $table->string('last_data_hash')->nullable()->index();
            $table->timestamp('last_audited_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_insights');
    }
};
