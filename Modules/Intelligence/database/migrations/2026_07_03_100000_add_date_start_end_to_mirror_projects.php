<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intelligence_mirror_projects', function (Blueprint $table) {
            $table->date('date_start')->nullable()->after('state');
            $table->date('date_end')->nullable()->after('date_start');
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_mirror_projects', function (Blueprint $table) {
            $table->dropColumn(['date_start', 'date_end']);
        });
    }
};
