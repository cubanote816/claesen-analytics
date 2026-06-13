<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // profit_percent can exceed 9999% on projects with minimal costs — widen to decimal(10,4).
    // Example: P20180031 NMBS cost=€920, invoiced=€110,005 → profit_percent=11,852%.

    public function up(): void
    {
        Schema::table('intelligence_mirror_project_results', function (Blueprint $table) {
            $table->decimal('profit_percent', 10, 4)->nullable()->change();
            $table->decimal('profit_percent_estimates', 10, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_mirror_project_results', function (Blueprint $table) {
            $table->decimal('profit_percent', 8, 4)->nullable()->change();
            $table->decimal('profit_percent_estimates', 8, 4)->nullable()->change();
        });
    }
};
