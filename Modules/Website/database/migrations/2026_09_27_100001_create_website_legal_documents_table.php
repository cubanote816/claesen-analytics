<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLA-479 (dynamic part) / P-CLA-29 hybrid decision — odd/reports/backend-team-prompt.md §4.1a.
 *
 * One row per legal document per site (`doc_id` ∈ privacy|cookies|terms),
 * editable from the Bertels panel and served publicly via
 * GET /v1/website/legal/{docId}. `title`/`body` are locale-keyed JSON
 * (nl/en/fr/de) handled through Spatie HasTranslations + HasAiTranslations.
 *
 * ADR D3 (docs/ai/adr-multi-organization.md): site-owned entities carry ONLY
 * `site_id` — the organization is always derived through
 * sites.organization_id, never a second foreign key on this table.
 *
 * `body` is PLAIN TEXT (paragraphs separated by \n\n): the Astro frontend
 * has no `set:html` and its Zod schemas require plain strings
 * (backend-requirements.md §7.6). Enforcement lives at the API/Filament
 * layer, not in the DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_legal_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('doc_id', 50);
            $table->json('title');
            $table->json('body');
            $table->string('version', 50);
            $table->date('effective_date');
            $table->timestamps();

            $table->unique(['site_id', 'doc_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_legal_documents');
    }
};
