<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // labor_id can be null in legacy followup_labor_analytical rows
        Schema::table('intelligence_mirror_labor', function (Blueprint $table) {
            $table->integer('labor_id')->nullable()->change();
        });

        // total_price_vat_excl can be null in legacy invoice rows (old/archived invoices)
        Schema::table('intelligence_mirror_invoices', function (Blueprint $table) {
            $table->decimal('total_price_vat_excl', 14, 4)->nullable()->change();
        });

        // type (MAMO) can be null in very old followup_cost rows (pre-2010 legacy data)
        Schema::table('intelligence_mirror_costs', function (Blueprint $table) {
            $table->string('type', 1)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_mirror_labor', function (Blueprint $table) {
            $table->integer('labor_id')->nullable(false)->change();
        });

        Schema::table('intelligence_mirror_invoices', function (Blueprint $table) {
            $table->decimal('total_price_vat_excl', 14, 4)->nullable(false)->change();
        });

        Schema::table('intelligence_mirror_costs', function (Blueprint $table) {
            $table->string('type', 1)->nullable(false)->change();
        });
    }
};
