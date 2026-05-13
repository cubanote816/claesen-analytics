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
        Schema::table('safety_questions', function (Blueprint $table) {
            $table->boolean('allow_yes')->default(true)->after('text_nl');
            $table->boolean('allow_no')->default(true)->after('allow_yes');
            $table->boolean('allow_na')->default(true)->after('allow_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('safety_questions', function (Blueprint $table) {
            $table->dropColumn(['allow_yes', 'allow_no', 'allow_na']);
        });
    }
};
