<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('safety_questions', function (Blueprint $table) {
            $table->string('category')->nullable()->after('text_nl');
        });
    }

    public function down(): void
    {
        Schema::table('safety_questions', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
