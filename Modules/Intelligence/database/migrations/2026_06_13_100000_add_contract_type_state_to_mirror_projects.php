<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intelligence_mirror_projects', function (Blueprint $table) {
            $table->decimal('contract_price', 12, 2)->nullable()->after('fl_active');
            $table->tinyInteger('type')->nullable()->after('contract_price');   // raw project.type (0-8)
            $table->smallInteger('state')->nullable()->after('type');           // raw project.state
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_mirror_projects', function (Blueprint $table) {
            $table->dropColumn(['contract_price', 'type', 'state']);
        });
    }
};
