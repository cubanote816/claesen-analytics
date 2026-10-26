<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4/CLA-475 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "Consentimiento y versión de política almacenados cuando corresponda."
 * Both nullable — storing consent is opt-in from the caller
 * (Modules\Website\Services\ConsultationService::createRequest() only
 * populates them when the intake payload actually sent a truthy
 * `consent`), never a hard requirement: the separate Astro frontend does
 * not send this field yet, and making it mandatory server-side today
 * would reject every real submission the moment this ships. Whether to
 * require consent going forward is a product/legal decision, same
 * deliberately-out-of-scope treatment CLA-476 gave the Belgian legal
 * review of retention periods — not made here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_consultation_requests', function (Blueprint $table): void {
            $table->timestamp('consent_given_at')->nullable()->after('anonymized_at');
            $table->string('consent_policy_version', 50)->nullable()->after('consent_given_at');
        });
    }

    public function down(): void
    {
        Schema::table('website_consultation_requests', function (Blueprint $table): void {
            $table->dropColumn(['consent_given_at', 'consent_policy_version']);
        });
    }
};
