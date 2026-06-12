<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_mirror_estimate_calc', function (Blueprint $table) {
            $table->string('estimate_id', 20)->primary(); // 1:1 with estimate

            // MAMO factors (markup multipliers per cost category)
            $table->decimal('factor_material', 8, 4)->default(0);
            $table->decimal('factor_labor', 8, 4)->default(0);
            $table->decimal('factor_equipment', 8, 4)->default(0);
            $table->decimal('factor_subcontract', 8, 4)->default(0);

            // Quantity and unit-price adjustment factors
            $table->decimal('factor_qty_labor', 8, 4)->default(0);
            $table->decimal('factor_qty_material', 8, 4)->default(0);
            $table->decimal('factor_unitprice', 8, 4)->default(0);

            // Labor cost inputs
            $table->decimal('labor_c_price', 10, 2)->nullable();
            $table->decimal('additional_hours', 10, 2)->nullable();
            $table->smallInteger('qty_employees')->nullable();

            // Extra costs (transport, management, insurance, etc.) as JSON to avoid column proliferation
            $table->json('extra_costs_json')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_mirror_estimate_calc');
    }
};
