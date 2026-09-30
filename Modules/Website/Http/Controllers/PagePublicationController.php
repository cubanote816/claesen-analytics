<?php

declare(strict_types=1);

namespace Modules\Website\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Services\OrganizationContext;
use Modules\Website\Models\PagePublication;

/**
 * The publication manifest the static site's build reads (CLA-611 + the gate in
 * `electro-bertels-official`'s `src/routes/publication.ts`).
 *
 * The shape is the snapshot file **verbatim** — `{version, published}` — and it
 * is deliberately **not** wrapped in the module's usual `{data: …}` envelope:
 * the consumer is the sync, which writes this response straight to
 * `src/content/publication.json`. An envelope would only add a mapping step that
 * can drift, and nothing else consumes this endpoint.
 *
 * It is also deliberately **deterministic**: no `generatedAt`, no counts. The
 * snapshot is committed, so it should change when the publication state changes
 * and not because a build ran at a different minute.
 *
 * ⚠️ An **empty** manifest is a valid answer (a site with nothing approved) and a
 * dangerous one: written over the snapshot it would turn the whole site into a
 * draft. The sync must refuse it — see
 * `odd/tasks/cla-611-publication-manifest.md`.
 */
class PagePublicationController extends Controller
{
    public function manifest(): JsonResponse
    {
        $site = app(OrganizationContext::class)->site();

        // The `v1/website` group always resolves a site (ResolveRequestSite); if
        // it could not, the request is not about any site and there is nothing
        // truthful to answer.
        abort_if($site === null, 404);

        return response()->json([
            'version' => 1,
            // Cast so "nothing is approved" serialises as `{}` rather than `[]`:
            // it is a map of pages, and an empty list would read as a different
            // kind of value.
            'published' => (object) PagePublication::manifestFor($site),
        ]);
    }
}
