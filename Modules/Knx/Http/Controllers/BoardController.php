<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\ProjectBoardsResource;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxProject;

/**
 * The cabinets of a project (KNX-3, docs/BACKEND-API.md §4.3).
 *
 * The office's Verdelers tab used to be fed by a generated import of the project's
 * worklist. This endpoint serves the same data from the database, and its board `id`
 * is the identity a plan marker points at.
 *
 * A project whose worklist has not been imported answers with an empty `boards` array:
 * that is a real state (nothing to show yet), not an error.
 */
class BoardController extends Controller
{
    public function index(Request $request, string $code): array
    {
        $project = KnxProject::query()->where('code', $code)->firstOrFail();

        $boards = KnxBoard::query()
            ->where('project_id', $project->getKey())
            ->with(['modules.links'])
            ->orderBy('id')
            ->get();

        return ProjectBoardsResource::make([
            'project' => $project,
            'boards' => $boards,
        ])->resolve($request);
    }
}
