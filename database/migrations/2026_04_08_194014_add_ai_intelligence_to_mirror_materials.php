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
        Schema::table('analytics_mirror_materials', function (Blueprint $table) {
            $table->string('category_ai')->nullable()->after('description');
            $table->json('tags')->nullable()->after('category_ai');
            $table->text('usage_summary')->nullable()->after('tags');
            $table->string('modern_id')->nullable()->after('usage_summary');
            $table->timestamp('last_learned_at')->nullable()->after('modern_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics_mirror_materials', function (Blueprint $table) {
            $table->dropColumn(['category_ai', 'tags', 'usage_summary', 'modern_id', 'last_learned_at']);
        });
    }
};
