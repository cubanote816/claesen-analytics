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
        Schema::create('employee_insights', function (Blueprint $table) {
            $table->id();

            // Link to Employee (Legacy ID is string)
            $table->string('employee_id')->unique()->index();

            // AI Classification (Archetypes)
            $table->string('archetype_label')->nullable(); // e.g., 'The Diesel'
            $table->string('archetype_icon')->nullable();  // e.g., '🚜'

            // Performance Metrics
            $table->string('efficiency_trend')->nullable(); // UP, DOWN, STABLE
            $table->integer('burnout_risk_score')->default(0); // 0-100

            // AI Content
            $table->text('manager_insight')->nullable(); // Short advice for management
            $table->text('ai_analysis')->nullable();     // Detailed markdown analysis
            $table->json('full_performance_snapshot')->nullable(); // JSON of data used for audit

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
        Schema::dropIfExists('employee_insights');
    }
};
