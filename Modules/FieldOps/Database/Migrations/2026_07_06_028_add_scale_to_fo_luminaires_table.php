<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fo_luminaires', function (Blueprint $table) {
            $table->float('scale_x')->nullable()->after('frame_y');
            $table->float('scale_y')->nullable()->after('scale_x');
        });
    }

    public function down(): void
    {
        Schema::table('fo_luminaires', function (Blueprint $table) {
            $table->dropColumn(['scale_x', 'scale_y']);
        });
    }
};
