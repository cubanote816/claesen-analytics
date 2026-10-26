<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1/P2 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Nullable on purpose: NOT NULL lands only at the end of phase P7 (ADR
 * decision D2), once every user in every environment has been backfilled
 * (core:backfill-user-organizations, CLA-459) and the whole enforcement
 * chain (P3-P5) is verified. restrictOnDelete matches sites.organization_id
 * from phase P1 — an organization with users attached can't be deleted out
 * from under them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('organizations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
