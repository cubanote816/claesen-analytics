<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intelligence_mirror_projects', function (Blueprint $table) {
            $table->text('project_address_text')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_mirror_projects', function (Blueprint $table) {
            $table->dropColumn('project_address_text');
        });
    }
};
