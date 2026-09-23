<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1/P3b of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Second half of phase P3, deliberately deferred by CLA-547 (P3a). Decision D3
 * marks this table `site_id (UNIQUE)`, not a plain `site_id` like
 * website_projects/website_consultation_requests: the row was addressed by a
 * hardcoded `id = 1` because the installation only ever published one site.
 * A unique site_id is the structural replacement for that hardcoded PK — one
 * publication-state row per site, still exactly one row for Claesen alone.
 *
 * Nullable on purpose, same rationale as CLA-547: NOT NULL lands at the end of
 * phase P7 together with users.organization_id and the other site_id columns.
 *
 * restrictOnDelete matches every other FK this program has added onto sites.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_publication_states', function (Blueprint $table) {
            $table->foreignId('site_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('sites')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // MySQL 8 would pick the unique index as the sole backing index for
        // the FK once both exist, and dropping it first fails with 1553
        // "needed in a foreign key constraint" — the same failure CLA-547 hit
        // for website_projects_site_id_slug_unique and CLA-523 hit for
        // fo_luminaires_one_active_per_position. Drop the FK (which also
        // drops its own index) before the unique constraint, so there is
        // never a moment where the column has neither a backing index it
        // needs nor a leftover one MySQL refuses to remove.
        Schema::table('website_publication_states', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
        });

        Schema::table('website_publication_states', function (Blueprint $table) {
            $table->dropUnique(['site_id']);
            $table->dropColumn('site_id');
        });
    }
};
