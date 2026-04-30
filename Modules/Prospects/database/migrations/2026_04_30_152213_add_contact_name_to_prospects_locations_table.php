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
            $table->string('contact_name')->nullable()->after('contact_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects_locations', function (Blueprint $table) {
            $table->dropColumn('contact_name');
        });
    }
};
