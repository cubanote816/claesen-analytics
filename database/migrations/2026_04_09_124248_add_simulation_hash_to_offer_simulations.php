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
        Schema::table('intelligence_offer_simulations', function (Blueprint $table) {
            $table->string('simulation_hash')->nullable()->index()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = Schema::hasTable('intelligence_offer_simulations')
            ? 'intelligence_offer_simulations'
            : 'analytics_offer_simulations';

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('simulation_hash');
        });
    }
};
