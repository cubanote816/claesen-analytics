<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxProject;

/**
 * The cabinets of a project with their modules, channels and links (KNX-3, KNX-52).
 *
 * `boards[].id` is the board's **external identity** — the worklist slug when the board
 * came from an import, its short code otherwise — and it is exactly the string a plan
 * marker's `boardId` carries. That coupling is the reason the boards endpoint and the
 * markers ship in the same contract: a marker has to point at the same board the
 * Verdelers panel shows.
 *
 * Each board is rendered by `BoardResource`, which the single-board responses reuse, so
 * the read shape and the write shape cannot drift.
 *
 * The resource's "model" is the payload array (`project`, `boards`), because the shape is
 * a nested projection and not a single row.
 */
class ProjectBoardsResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        /** @var KnxProject $project */
        $project = $this->resource['project'];

        return [
            'projectCode' => $project->code,
            'boards' => $this->resource['boards']
                ->map(fn (KnxBoard $board): array => BoardResource::make($board)->resolve($request))
                ->values()
                ->all(),
        ];
    }
}
