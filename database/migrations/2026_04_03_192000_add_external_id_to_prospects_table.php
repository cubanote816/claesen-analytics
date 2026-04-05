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
        Schema::table('prospects_prospects', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('id')->index();
            $table->unique(['external_id', 'federation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects_prospects', function (Blueprint $table) {
            $table->dropUnique(['external_id', 'federation']);
            $table->dropColumn('external_id');
        });
    }
};
