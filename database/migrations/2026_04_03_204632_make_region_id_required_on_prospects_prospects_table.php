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
        // Give a default region (id 11 is Brussel) to any existing nulls
        \Illuminate\Support\Facades\DB::table('prospects_prospects')->whereNull('region_id')->update(['region_id' => 11]);

        Schema::table('prospects_prospects', function (Blueprint $table) {
            // Drop foreign key if it exists under default name, wait Laravel 10/11 dropForeign takes an array and builds name
            $table->dropForeign(['region_id']);
        });

        Schema::table('prospects_prospects', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable(false)->change();
            $table->foreign('region_id')->references('id')->on('prospects_regions')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects_prospects', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
        });

        Schema::table('prospects_prospects', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable(true)->change();
            $table->foreign('region_id')->references('id')->on('prospects_regions')->onDelete('set null');
        });
    }
};
