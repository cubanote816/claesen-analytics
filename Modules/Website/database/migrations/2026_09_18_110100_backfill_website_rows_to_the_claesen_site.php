<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1/P3a of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Data migration, kept separate from the structure one (decision D7), and
 * written with `DB::table()` rather than Eloquent on purpose. Every model
 * involved here has observers that would fire on a mass Eloquent update:
 *
 *   - ProjectObserver            → requests a full static-site rebuild
 *   - HasAiTranslations (Project) → dispatches Gemini translation jobs
 *   - ConsultationRequest        → LogsActivity writes an activity_log row
 *
 * A backfill is bookkeeping; it must not rebuild the public website or spend
 * AI credits. `DB::table()` also reaches soft-deleted projects, which the
 * Eloquent query would silently skip.
 *
 * Idempotent: only touches rows whose site_id is still null. `down()` is a
 * deliberate no-op — no `down()` in this program deletes data, and clearing
 * the column would be indistinguishable from data loss for anyone who had
 * already assigned rows deliberately.
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

        foreach (['website_projects', 'website_consultation_requests'] as $table) {
            DB::table($table)->whereNull('site_id')->update(['site_id' => $claesenSiteId]);
        }
    }

    public function down(): void
    {
        // No-op: see the class docblock.
    }
};
