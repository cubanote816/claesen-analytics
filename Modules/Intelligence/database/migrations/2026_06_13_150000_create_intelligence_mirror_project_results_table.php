<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Column name mapping (SQL Server → mirror):
    //   project_relation_id   → relation_id
    //   project_relation_name → relation_name
    //   oH                    → oh  (MySQL lowercase convention; same value)
    //
    // invoiced here is a FLOAT AMOUNT (€), NOT a boolean.
    // Distinct from intelligence_mirror_costs.invoiced which is boolean.

    public function up(): void
    {
        Schema::create('intelligence_mirror_project_results', function (Blueprint $table) {
            $table->string('project_id', 20)->primary();
            $table->string('project_name')->nullable();
            $table->integer('relation_id')->nullable();
            $table->string('relation_name')->nullable();
            $table->string('dossier', 50)->nullable();

            // Cost breakdown (decimal — not float — for precision)
            $table->decimal('costprice_material', 14, 4)->nullable();
            $table->decimal('costprice_labor', 14, 4)->nullable();
            $table->decimal('costprice_equipment', 14, 4)->nullable();
            $table->decimal('costprice_subcontract', 14, 4)->nullable();
            $table->decimal('costprice_extra', 14, 4)->nullable();
            $table->decimal('costprice_transport', 14, 4)->nullable();
            $table->decimal('costprice_total', 14, 4)->nullable();

            // Financial result
            $table->decimal('invoiced', 14, 4)->nullable();       // invoiced amount in €
            $table->decimal('profit', 14, 4)->nullable();
            $table->decimal('profit_percent', 8, 4)->nullable();
            $table->decimal('profit_percent_estimates', 8, 4)->nullable();
            $table->decimal('total_estimates', 14, 4)->nullable();
            $table->decimal('total_regie', 14, 4)->nullable();

            // Hours
            $table->decimal('hours_regie', 10, 2)->nullable();
            $table->decimal('oh', 10, 2)->nullable();              // oH in SQL Server
            $table->decimal('project_uren', 10, 2)->nullable();
            $table->decimal('voorz_uren', 10, 2)->nullable();
            $table->decimal('uren_projectleader', 10, 2)->nullable();

            $table->boolean('current_costs_booked')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_mirror_project_results');
    }
};
