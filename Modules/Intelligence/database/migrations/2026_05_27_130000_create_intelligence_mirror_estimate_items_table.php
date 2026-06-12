<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirror of CAFCA's estimate_item + project_estimates tables.
     *
     * This is the most valuable training data for the AI offer simulator:
     * it contains the actual line structure of every real offer Claesen
     * built in CAFCA. Without this data the AI invents offer structures
     * instead of learning from proven patterns.
     *
     * NOTE: The exact column names from SQL Server's estimate_item table
     * must be confirmed via the auditor query before running the full sync:
     *   SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
     *   WHERE TABLE_NAME = 'estimate_item' ORDER BY ORDINAL_POSITION
     *
     * If columns differ from what is mapped in SyncMirrorDataService::syncEstimateItems(),
     * create an adjustment migration before syncing production data.
     *
     * AUDITOR GATE: DEBERÍAS CONSULTAR AL AUDITOR before running the sync
     * to confirm column mapping is correct.
     */
    public function up(): void
    {
        Schema::create('intelligence_mirror_estimate_items', function (Blueprint $table) {
            $table->id();

            // Links back to the CAFCA offer/estimate
            $table->string('estimate_id', 30)->index();
            $table->string('project_id', 15)->index();

            // Line ordering within the estimate
            $table->unsignedSmallInteger('sequence')->default(0);

            // Line classification: chapter header, sub-header, item line, or free text
            $table->string('line_type', 20)->nullable()
                ->comment('titulo | subtitulo | partida | tekst');

            // Material reference and description
            $table->string('ref', 30)->nullable()->index();
            $table->text('description')->nullable();

            // Quantities and pricing
            $table->decimal('quantity', 12, 4)->default(0);
            $table->string('unit', 10)->nullable();
            $table->decimal('unit_price_material', 14, 4)->default(0);
            $table->decimal('unit_price_labor', 14, 4)->default(0);
            $table->decimal('hours_per_unit', 10, 4)->default(0);
            $table->decimal('total_hours', 14, 4)->default(0);

            $table->timestamps();

            // Composite indexes for the most common query patterns
            $table->index(['project_id', 'sequence']);
            $table->index(['estimate_id', 'sequence']);
            $table->index(['project_id', 'line_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_mirror_estimate_items');
    }
};
