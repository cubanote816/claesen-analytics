<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_price_indices', function (Blueprint $table) {
            $table->id();

            // 'cpi_belgium' | 'labor_construction' | 'material_electrical' | 'material_civil'
            $table->string('category', 50);

            $table->smallInteger('year')->unsigned();
            $table->tinyInteger('month')->unsigned()->nullable(); // NULL = annual average

            // Index value relative to base_year (base_year = 100.00)
            $table->decimal('index_value', 10, 4);
            $table->smallInteger('base_year')->unsigned()->default(2021);

            $table->string('source', 100); // 'Statbel' | 'NBB' | 'ABEX'
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['category', 'year', 'month'], 'uq_price_index_period');
            $table->index(['category', 'year'], 'idx_price_index_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_price_indices');
    }
};
