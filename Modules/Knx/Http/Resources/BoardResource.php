<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxBoardModule;

/**
 * One cabinet with its modules and links (KNX-52).
 *
 * `id` is the board's **external identity** — the worklist slug, or its short code for a
 * board the office created — and it is exactly the string a plan marker's `boardId`
 * carries. `origin`/`reviewState`/`updatedBy`/`updatedAt` are the audit trail: who wrote
 * the board and whether the office confirmed it.
 *
 * @property-read KnxBoard $resource
 */
class BoardResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        $board = $this->resource;

        return [
            'id' => (string) ($board->slug ?? $board->code),
            'code' => $board->code,
            'name' => (string) ($board->name ?? $board->code),
            'floor' => $board->floor,
            'origin' => $board->origin,
            'reviewState' => $board->review_state,
            'updatedBy' => $board->updatedBy?->shortName(),
            'updatedAt' => $board->updated_at?->toIso8601String(),
            'modules' => $board->modules
                ->map(fn (KnxBoardModule $module): array => BoardModuleResource::make($module)->resolve($request))
                ->values()
                ->all(),
        ];
    }
}
