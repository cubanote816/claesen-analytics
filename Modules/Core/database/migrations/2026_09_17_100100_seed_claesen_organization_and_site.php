<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F0/P1 data step of the multi-organization program — see
 * docs/ai/adr-multi-organization.md, decision D7: structure and data are
 * separate, versioned migrations, never a seeder. deploy.sh only ever runs
 * `migrate --force`, never a seeder (lesson from CLA-496), so this
 * idempotent data migration is the only safe place for the Claesen
 * bootstrap row to land on every environment, including production.
 *
 * DB::table() only, never Eloquent — even though Organization/Site have no
 * observers today, this program's own rule (D7) is that backfills never go
 * through models, because on other tables that silently triggers side
 * effects (ProjectObserver's rebuild webhook, HasAiTranslations,
 * LogsActivity). Staying consistent with that rule here, where it happens
 * to be harmless, avoids a special case to remember once it matters.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $organizationId = DB::table('organizations')->where('slug', 'claesen')->value('id');

        if ($organizationId === null) {
            $organizationId = DB::table('organizations')->insertGetId([
                'slug' => 'claesen',
                'name' => 'Claesen Verlichting',
                'status' => 'active',
                'retention_policy' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $siteExists = DB::table('sites')->where('key', 'claesen-verlichting')->exists();

        if (! $siteExists) {
            DB::table('sites')->insert([
                'organization_id' => $organizationId,
                // Locales and default match the project-wide canonical set
                // (nl/en/fr/de, APP_LOCALE=nl) already established across
                // FieldOps and Website. Domain stays null: the real public
                // frontend hostname is not in versioned config yet (ADR §8).
                'key' => 'claesen-verlichting',
                'domain' => null,
                'default_locale' => 'nl',
                'locales' => json_encode(['nl', 'en', 'fr', 'de']),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Deliberately non-destructive (ADR "Rollback Plan"): the Claesen row
     * is the bootstrap of the whole program, not disposable data — every
     * later phase (P2+) assumes it exists. If it ever needs to be removed,
     * that is its own deliberate migration, never this down().
     */
    public function down(): void
    {
        // No-op by design.
    }
};
