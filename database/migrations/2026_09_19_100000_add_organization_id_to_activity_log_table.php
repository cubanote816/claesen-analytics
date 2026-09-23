<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2/CLA-465 of the multi-organization program — docs/ai/adr-multi-organization.md,
 * decision D3 ("`activity_log` | `organization_id` | Hay eventos de plataforma
 * sin sitio"). Unlike Website's `site_id` (D3 rejects it there in favour of
 * deriving the organization through `sites.organization_id`), the audit trail
 * is platform-wide and not every entry has a site — it gets its own direct
 * `organization_id` column.
 *
 * Nullable and never backfilled for historical rows on purpose: an existing
 * entry's causer may no longer exist, may not be a User, or the causer's own
 * organization at write time is unrecoverable after the fact — inventing an
 * organization for old rows would misrepresent the audit trail. Every NEW
 * entry gets it filled automatically going forward (Modules\Core\Models\
 * ActivityLogEntry::creating(), the activity_model this migration's sibling
 * config change points to).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('causer_id')
                ->constrained('organizations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
