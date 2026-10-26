<?php

declare(strict_types=1);

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\BelongsToSite;
use Modules\Intelligence\Services\TranslationStateService;
use Modules\Intelligence\Traits\HasAiTranslations;
use Spatie\Translatable\HasTranslations;

/**
 * CLA-479 (dynamic part) — per-site legal document (privacy/cookies/terms),
 * editable from the owning site's panel and served publicly via
 * GET /v1/website/legal/{docId}.
 *
 * ADR D3: this site-owned table carries only `site_id` (UNIQUE(site_id,
 * doc_id)); the organization is derived through sites.organization_id.
 *
 * `title`/`body` are locale-keyed JSON (nl/en/fr/de). `body` is PLAIN TEXT
 * — paragraphs separated by "\n\n", no HTML/Markdown — because the Astro
 * frontend renders text only and its Zod schemas are z.string()
 * (backend-requirements.md §7.6).
 *
 * Translation state: `translationStatusFor()` derives the per-(attribute,
 * locale) state from translation presence for now; CLA-611 WU5 replaces
 * this derivation with the real per-locale state engine
 * (Modules\Intelligence) without changing this endpoint's wire contract.
 */
class LegalDocument extends Model
{
    use BelongsToSite;
    use HasAiTranslations;
    use HasFactory;
    use HasTranslations;

    /** Stable public identifiers — slugs are copy and live in the frontend. */
    public const DOC_IDS = ['privacy', 'cookies', 'terms'];

    protected $table = 'website_legal_documents';

    protected $fillable = [
        'site_id',
        'doc_id',
        'title',
        'body',
        'version',
        'effective_date',
    ];

    public array $translatable = ['title', 'body'];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function getAiTranslatableAttributes(): array
    {
        return ['title', 'body'];
    }

    /**
     * CLA-611 (gap G4): context injected into the translation prompt.
     * Optional HasAiTranslations hook — models without it translate with
     * no extra context.
     */
    public function getAiTranslationContext(): string
    {
        return "Legal document '{$this->doc_id}' (version {$this->version}) for a Belgian electrical contractor's public website. Formal legal tone, plain text output — never HTML or Markdown.";
    }

    /**
     * Per-locale translation status for the public payload
     * (missing|machine|stale|needs_review|reviewed|published|failed).
     *
     * CLA-611: DERIVED, not declared — the worst per-(attribute, locale)
     * state across this document's translatable keys. A document is only
     * publishable in a locale when BOTH title and body are
     * reviewed|published; one unapproved key keeps the whole locale in
     * draft. Legacy data without state rows derives from translation
     * presence (never silently approved).
     */
    public function translationStatusFor(string $locale): string
    {
        return app(TranslationStateService::class)
            ->entityStatusForLocale($this, ['title', 'body'], $locale);
    }
}
