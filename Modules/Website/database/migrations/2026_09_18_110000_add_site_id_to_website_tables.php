<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1/P3a of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * `site_id` is the single source of truth on site-owned rows (decision D3):
 * the organization is derived through `sites.organization_id` and is never
 * stored again on these tables, which is what makes the invalid combination
 * "Claesen organization + Bertels site" unrepresentable.
 *
 * Nullable on purpose. NOT NULL lands at the end of phase P7 together with
 * `users.organization_id`, once every environment is backfilled and the whole
 * enforcement chain (P5) is verified — a NOT NULL here today would turn any
 * creation path that forgets the column into a 500 in the panel.
 *
 * restrictOnDelete matches `sites.organization_id` (P1) and
 * `users.organization_id` (P2): a site with content attached cannot be
 * deleted out from under it.
 *
 * `website_publication_states` is deliberately NOT part of this migration —
 * it belongs to P3b together with the singleton change in PublicationState.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_projects', function (Blueprint $table) {
            $table->foreignId('site_id')
                ->nullable()
                ->after('id')
                ->constrained('sites')
                ->restrictOnDelete();
        });

        Schema::table('website_consultation_requests', function (Blueprint $table) {
            $table->foreignId('site_id')
                ->nullable()
                ->after('id')
                ->constrained('sites')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('website_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
        });

        Schema::table('website_consultation_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
        });
    }
};
