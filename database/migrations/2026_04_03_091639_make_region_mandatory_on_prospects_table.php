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
        // 1. Backfill NULL values first
        Illuminate\Support\Facades\DB::table('prospects')->whereNull('region')->update(['region' => 'Antwerpen']);

        // 2. Change column to NOT NULL
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('region')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('region')->nullable()->change();
        });
    }
};
