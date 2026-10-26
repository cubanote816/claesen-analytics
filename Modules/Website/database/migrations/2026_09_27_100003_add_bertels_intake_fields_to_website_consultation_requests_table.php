<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLA-484: fields the real Electro Bertels contact form sends that the
 * intake previously discarded (backend-requirements.md §9.2).
 *
 * All nullable: the Claesen frontend does not send any of them and its
 * payload/behaviour stays byte-identical (zero-regression rule). `segment`
 * mirrors the form's radiogroup (particulier|bedrijf|industrie) and lives
 * beside the existing Claesen-side `type`; `locale` records the language
 * the visitor wrote in so the reply goes out in the correct language;
 * `consent_version` (the accepted consent-text version) reuses the existing
 * `consent_policy_version` column — no new column for the same fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_consultation_requests', function (Blueprint $table): void {
            $table->string('segment', 20)->nullable()->after('project_type');
            $table->string('postal_code', 16)->nullable()->after('segment');
            $table->string('subject', 255)->nullable()->after('postal_code');
            $table->string('locale', 5)->nullable()->after('subject');
        });
    }

    public function down(): void
    {
        Schema::table('website_consultation_requests', function (Blueprint $table): void {
            $table->dropColumn(['segment', 'postal_code', 'subject', 'locale']);
        });
    }
};
