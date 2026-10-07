<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxProject;

/**
 * A project as the field app may see it (V11.b, CLA-609).
 *
 * Scope is not re-decided here: `FieldTodayService::isAssignedToday()` is the one
 * answer to "may this technician touch this project today", and every `/field/*`
 * endpoint that takes a project code goes through this class so the rule cannot
 * drift between endpoints.
 *
 * The two failures stay different on purpose:
 *   - unknown code      → 404, the project does not exist;
 *   - not planned today → 403, it exists and is simply not theirs today.
 * Collapsing them would make a typo look like a permission problem.
 */
class FieldProjectService
{
    /**
     * The document kinds the field app can open: both are drawings.
     *
     * `ETS` (a .knxproj export), `Keuring` (an inspection report) and `Foto's` (a
     * photo archive) are documents, not plans — the app's own fixture lists only
     * `Plan` and `Schema` under its plans.
     */
    private const DRAWING_KINDS = ['Plan', 'Schema'];

    public function __construct(private readonly FieldTodayService $today) {}

    public function resolveAuthorized(KnxEmployee $technician, string $code): KnxProject
    {
        $project = KnxProject::query()
            ->with([
                'client',
                // Ordered explicitly: the rooms carry an index on (project_id, name),
                // so without this MySQL answers an alphabetical list and both the
                // room picker and the floor list would come out in the wrong order.
                'rooms' => fn ($query) => $query->orderBy('id'),
                'boards' => fn ($query) => $query->orderBy('id'),
            ])
            ->where('code', $code)
            ->firstOrFail();

        if (! $this->today->isAssignedToday($technician, $project)) {
            throw new AuthorizationException('You are not planned on this project today.');
        }

        return $project;
    }

    /**
     * The project's drawings, in fixture order.
     *
     * Two filters, both deliberate:
     *   - only the drawing kinds above, so the technician's plan list is not a
     *     document browser;
     *   - only documents whose file is actually on disk, because the app downloads
     *     and caches the file itself — a plan it cannot open is worse than a plan
     *     it cannot see. A document whose type we cannot name is left out too: the
     *     app would pick a renderer from the MIME type and would pick wrong.
     *
     * @return Collection<int, KnxDocument>
     */
    public function plansFor(KnxProject $project): Collection
    {
        return KnxDocument::query()
            ->with('project')
            ->where('project_id', $project->getKey())
            ->whereIn('kind', self::DRAWING_KINDS)
            ->orderBy('id')
            ->get()
            ->filter(fn (KnxDocument $document): bool => $document->resolvedMimeType() !== null
                && $document->path !== null
                && Storage::disk('local')->exists($document->path))
            ->values();
    }

    /**
     * The project's devices, both the ones registered from site and the plan's own.
     *
     * Same two ordering rules as the office list (`ProjectController::devices()`):
     * field registrations first, then the ETS plan, each group in address order. Done
     * in PHP rather than in SQL so it does not depend on a database-specific
     * `ORDER BY FIELD()`.
     *
     * Both are returned on purpose. The app draws progress ("17 of 24") and the
     * per-room counts from this list, and a plan-only list would report zero work
     * done on a project that is half finished.
     *
     * @return Collection<int, KnxDevice>
     */
    public function devicesFor(KnxProject $project): Collection
    {
        return $project->devices()
            ->with(['room', 'board', 'registeredBy'])
            ->get()
            ->sortBy(fn (KnxDevice $device): array => [
                $device->source === KnxDevice::SOURCE_FIELD ? 0 : 1,
                $device->address,
            ])
            ->values();
    }
}
