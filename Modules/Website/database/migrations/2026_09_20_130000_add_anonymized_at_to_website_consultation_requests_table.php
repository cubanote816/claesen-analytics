<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4/CLA-476 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Marks a lead as GDPR-anonymized (Modules\Website\Models\ConsultationRequest::
 * anonymize(), driven by Modules\Website\Services\RetentionService — either the
 * automated retention job or an admin's on-demand erasure request). Nullable,
 * no data migration — nothing has ever been anonymized before this column
 * existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_consultation_requests', function (Blueprint $table): void {
            $table->timestamp('anonymized_at')->nullable()->after('first_response_at');
        });
    }

    public function down(): void
    {
        Schema::table('website_consultation_requests', function (Blueprint $table): void {
            $table->dropColumn('anonymized_at');
        });
    }
};
