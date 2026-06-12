<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formalizes AI-classification columns that BuildMaterialBrain and
     * MapWarehouseCategoriesCommand already write to at runtime but that
     * were never captured in a migration. Also fixes the discrepancy
     * between MirrorMaterial::$fillable and the actual table columns.
     */
    public function up(): void
    {
        Schema::table('intelligence_mirror_materials', function (Blueprint $table) {
            if (!Schema::hasColumn('intelligence_mirror_materials', 'category_ai')) {
                $table->string('category_ai')->nullable()->index()->after('fl_active');
            }

            if (!Schema::hasColumn('intelligence_mirror_materials', 'tags')) {
                $table->json('tags')->nullable()->after('category_ai');
            }

            if (!Schema::hasColumn('intelligence_mirror_materials', 'usage_summary')) {
                $table->text('usage_summary')->nullable()->after('tags');
            }

            if (!Schema::hasColumn('intelligence_mirror_materials', 'last_learned_at')) {
                $table->timestamp('last_learned_at')->nullable()->after('usage_summary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_mirror_materials', function (Blueprint $table) {
            $table->dropColumn(['category_ai', 'tags', 'usage_summary', 'last_learned_at']);
        });
    }
};
