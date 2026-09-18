<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F1/P3a of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * A slug is only unique within its own website. Without this swap, the first
 * Bertels project called "sportpark-noord" would collide with the Claesen one
 * and the two companies would be silently fighting over one namespace.
 *
 * Known and accepted gap while `site_id` stays nullable (NOT NULL is phase
 * P7): MySQL treats NULLs as distinct, so two rows with a null site_id and
 * the same slug would both be allowed. Mitigated by every creation path
 * setting the column, a test asserting zero null rows, and the iron rule
 * (D10) that no Bertels row exists before P7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_projects', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['site_id', 'slug']);
        });
    }

    public function down(): void
    {
        $duplicateSlugs = DB::table('website_projects')
            ->select('slug')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('slug');

        if ($duplicateSlugs->isNotEmpty()) {
            throw new RuntimeException(
                'Refusing to restore the site-agnostic UNIQUE(slug): these slugs exist on more than one site and '
                .'would collide — '.$duplicateSlugs->implode(', ').'. Resolve the duplicates first.'
            );
        }

        // MySQL 8 picked website_projects_site_id_slug_unique as the backing
        // index for the site_id FK once it existed (same 1553 "needed in a
        // foreign key constraint" failure CLA-523 fixed for
        // fo_luminaires_one_active_per_position) — give the FK a plain
        // single-column index to fall back on, in its own statement, before
        // dropping the composite one.
        Schema::table('website_projects', function (Blueprint $table) {
            $table->index('site_id');
        });

        Schema::table('website_projects', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'slug']);
            $table->unique('slug');
        });
    }
};
