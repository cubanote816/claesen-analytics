<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_projects', function (Blueprint $table) {
            // Work Details / In Action — all JSON for nl/en/fr/de i18n via HasTranslations
            $table->json('work_story')->nullable()->after('description');
            $table->json('challenge')->nullable()->after('work_story');
            $table->json('solution')->nullable()->after('challenge');
            $table->json('result')->nullable()->after('solution');
        });
    }

    public function down(): void
    {
        Schema::table('website_projects', function (Blueprint $table) {
            $table->dropColumn(['work_story', 'challenge', 'solution', 'result']);
        });
    }
};
