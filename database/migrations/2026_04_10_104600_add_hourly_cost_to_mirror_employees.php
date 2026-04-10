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
        Schema::table('analytics_mirror_employees', function (Blueprint $table) {
            $table->decimal('hourly_cost', 15, 2)->default(0)->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics_mirror_employees', function (Blueprint $table) {
            $table->dropColumn('hourly_cost');
        });
    }
};
