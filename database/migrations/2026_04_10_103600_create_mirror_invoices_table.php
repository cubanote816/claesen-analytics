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
        Schema::create('analytics_mirror_invoices', function (Blueprint $blueprint) {
            $blueprint->string('id')->primary();
            $blueprint->string('project_id')->index();
            $blueprint->decimal('total_price_vat_excl', 15, 2);
            $blueprint->date('date');
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_mirror_invoices');
    }
};
