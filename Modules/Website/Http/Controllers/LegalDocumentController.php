<?php

declare(strict_types=1);

namespace Modules\Website\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Services\OrganizationContext;
use Modules\Website\Models\LegalDocument;
use Modules\Website\Services\PublicLocalePolicy;

/**
 * CLA-479 (dynamic part): GET /v1/website/legal/{docId} — the per-site legal
 * document (privacy/cookies/terms) for the request's resolved site.
 *
 * Same isolation model as SiteContentController: the route group's
 * ResolveRequestSite middleware fixes the site and LegalDocument's
 * BelongsToSite global scope does the row scoping — this controller never
 * touches OrganizationContext directly.
 *
 * Locale resolution obeys PublicLocalePolicy (site-scoped, CLA-611 G9):
 * strict sites get `null` for an untranslated locale — never another
 * locale's text. `translation_status` tells the frontend whether the
 * requested locale may be published (reviewed|published) or must stay in
 * draft (noindex + banner): missing|machine|stale|needs_review.
 */
class LegalDocumentController extends Controller
{
    public function show(string $docId): JsonResponse
    {
        // Unknown doc_id identifiers 404 — never an empty result leaking a
        // "not configured" state to the public build.
        if (! in_array($docId, LegalDocument::DOC_IDS, true)) {
            abort(404);
        }

        /** @var LegalDocument|null $document */
        $document = LegalDocument::query()
            ->where('doc_id', $docId)
            // Defense in depth: BelongsToSite's global scope is gated by
            // config('organizations.enforce') (ADR D4) and is inert today —
            // the resolved site id filters here regardless, so row isolation
            // on this public endpoint never depends on the enforcement flag.
            ->when(
                ($siteId = app(OrganizationContext::class)->siteId()) !== null,
                fn ($query) => $query->where('site_id', $siteId)
            )
            ->first();

        abort_if($document === null, 404);

        $locale = app()->getLocale();

        return response()->json([
            'data' => [
                'doc_id' => $document->doc_id,
                'version' => $document->version,
                'effective_date' => $document->effective_date->toDateString(),
                'locale' => $locale,
                'title' => PublicLocalePolicy::resolve($document->getTranslations('title'), $locale),
                'body' => PublicLocalePolicy::resolve($document->getTranslations('body'), $locale),
                'translation_status' => $document->translationStatusFor($locale),
            ],
        ]);
    }
}
