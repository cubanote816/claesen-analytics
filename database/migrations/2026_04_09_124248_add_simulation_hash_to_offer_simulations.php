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
        Schema::table('analytics_offer_simulations', function (Blueprint $table) {
            $table->string('simulation_hash')->nullable()->index()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics_offer_simulations', function (Blueprint $table) {
            $table->dropColumn('simulation_hash');
        });
    }
};
