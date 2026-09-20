<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4/CLA-477 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "SLA de primera respuesta medible" — first_response_at is stamped once
 * (Modules\Website\Services\ConsultationService::updateStatus()) the first
 * time a lead's status moves away from 'new', giving a real, queryable
 * duration (created_at -> first_response_at) instead of an unmeasurable
 * criterion. Structure only, no data migration needed here (nullable,
 * every existing row already had a non-null created_at to measure from
 * once this column starts getting populated going forward).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_consultation_requests', function (Blueprint $table): void {
            $table->timestamp('first_response_at')->nullable()->after('contacted_at');
        });
    }

    public function down(): void
    {
        Schema::table('website_consultation_requests', function (Blueprint $table): void {
            $table->dropColumn('first_response_at');
        });
    }
};
