<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fo_terrain_types', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('type');
            $table->string('pin_color', 7)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('fo_terrain_types', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'pin_color']);
        });
    }
};
