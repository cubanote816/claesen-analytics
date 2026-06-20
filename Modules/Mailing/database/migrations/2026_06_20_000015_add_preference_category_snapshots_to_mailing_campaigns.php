<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->string('template_category_snapshot', 20)->nullable()->after('body_snapshot');
            $table->string('preference_category_snapshot', 100)->nullable()->after('template_category_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->dropColumn(['template_category_snapshot', 'preference_category_snapshot']);
        });
    }
};
