<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1/P3b of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Data migration, kept separate from the structure one (decision D7), written
 * with DB::table() rather than Eloquent — same rationale as CLA-547's
 * equivalent backfill: a bookkeeping migration must not exercise application
 * side effects. PublicationState itself has no observers today, but going
 * through DB::table() keeps this migration consistent with the rest of the
 * program instead of relying on that being true forever.
 *
 * Idempotent: only touches rows whose site_id is still null. On a fresh
 * install this table has zero rows (PublicationState::current() creates the
 * row lazily on first use), so this is frequently a no-op — that is expected.
 *
 * down() is a deliberate no-op, same rationale as CLA-547: no down() in this
 * program deletes or blanks data that may have been deliberately assigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        $claesenSiteId = DB::table('sites')->where('key', 'claesen-verlichting')->value('id');

        if ($claesenSiteId === null) {
            throw new RuntimeException(
                'The Claesen bootstrap site row is missing — the phase P1 seed migration must run first.'
            );
        }

        DB::table('website_publication_states')->whereNull('site_id')->update(['site_id' => $claesenSiteId]);
    }

    public function down(): void
    {
        // No-op: see the class docblock.
    }
};
