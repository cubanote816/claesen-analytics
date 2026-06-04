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
        Schema::table('intelligence_mirror_employees', function (Blueprint $table) {
            $table->decimal('hourly_cost', 15, 2)->default(0)->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // After the rename migration's down() reverts tables to 'analytics_mirror_*',
        // this migration must use whichever name is current at rollback time.
        $tableName = Schema::hasTable('intelligence_mirror_employees')
            ? 'intelligence_mirror_employees'
            : 'analytics_mirror_employees';

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('hourly_cost');
        });
    }
};
