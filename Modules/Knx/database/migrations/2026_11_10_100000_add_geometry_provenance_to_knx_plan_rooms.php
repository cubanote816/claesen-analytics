<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance and topology of a plan room's polygon (KNX-48, re-entrega).
 *
 * All columns are nullable: rows saved before this change carry none of them and are
 * read back with the legacy defaults (`manual` if `source = manual`, else `area`; no
 * holes; no note), so nothing is rewritten and nothing is promoted to `contour`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_plan_rooms', function (Blueprint $table): void {
            // contour | area | manual
            $table->string('geometry', 10)->nullable()->after('state');
            // [[[nx, ny], …], …] — interior rings, fractions of the page.
            $table->json('holes')->nullable()->after('geometry');
            // area-mismatch | open | shared | in-barrier | too-small | no-vector
            $table->string('geometry_note', 16)->nullable()->after('holes');
            // Opaque tag of the client pipeline that produced the geometry.
            $table->string('processing_version', 32)->nullable()->after('geometry_note');
        });
    }

    public function down(): void
    {
        Schema::table('knx_plan_rooms', function (Blueprint $table): void {
            $table->dropColumn(['geometry', 'holes', 'geometry_note', 'processing_version']);
        });
    }
};
