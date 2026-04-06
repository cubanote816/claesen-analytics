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
        Schema::table('prospects_locations', function (Blueprint $table) {
            // Changing enum to string to avoid "Data truncated" errors
            // and provide flexibility for future contact types.
            $table->string('contact_type', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects_locations', function (Blueprint $table) {
            // Reverting back to enum with the initial 6 values
            $table->enum('contact_type', ['headquarters', 'stadium', 'venue_name', 'club_house', 'contact_person', 'other'])->change();
        });
    }
};
