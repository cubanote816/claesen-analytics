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
        Schema::rename('prospects', 'prospects_prospects');
        Schema::rename('prospect_locations', 'prospects_locations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('prospects_prospects', 'prospects');
        Schema::rename('prospects_locations', 'prospect_locations');
    }
};
