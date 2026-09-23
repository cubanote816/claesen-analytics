<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2/CLA-465 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Two of the six fields "Registra actor, organization, site, acción,
 * objeto, resultado y correlation_id" still asked for after organization_id
 * (CLA-465 first tramo). Actor/acción/objeto/resultado already exist via
 * Spatie's own causer/description/subject/properties columns.
 *
 * site_id: nullable, no FK — unlike organization_id (a real foreign key to
 * `organizations`), this is deliberately NOT a `sites` foreign key. It is
 * derived per-entry from the subject model's own `site_id` attribute when
 * that model has one (Modules\Core\Models\ActivityLogEntry::creating()) —
 * a subject-less activity (e.g. the panel-switch audit trail, CLA-460 cont.,
 * which logs with no subject at all) legitimately has no site, same as it
 * can have no organization. Adding a constrained FK here would force every
 * subject-less/non-site-scoped entry to carry a NULL through an FK column
 * for no referential-integrity benefit this table needs — it never joins
 * against `sites` for anything, only ever displays/filters the id.
 *
 * correlation_id: nullable string, populated by Modules\Core\Http\
 * Middleware\AssignCorrelationId (appended to the `web` group) so every
 * activity_log entry written during one HTTP request (panel or Livewire)
 * shares the same value — lets an incident review correlate "everything
 * this one request touched" across multiple log rows.
 *
 * No backfill for historical rows, same reasoning as organization_id's own
 * migration: neither value is recoverable after the fact for old entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id')->nullable()->after('organization_id');
            $table->string('correlation_id', 36)->nullable()->after('site_id');
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex(['correlation_id']);
            $table->dropColumn(['site_id', 'correlation_id']);
        });
    }
};
