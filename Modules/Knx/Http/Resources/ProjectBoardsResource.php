<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxBoardLink;
use Modules\Knx\Models\KnxBoardModule;
use Modules\Knx\Models\KnxProject;

/**
 * The cabinets of a project with their modules, channels and links (KNX-3,
 * docs/BACKEND-API.md §4.3).
 *
 * `boards[].id` is the board's **external identity** — the worklist slug when the
 * board came from an import, its short code otherwise — and it is exactly the string
 * a plan marker's `boardId` carries. That coupling is the reason the boards endpoint
 * and the markers ship in the same contract: a marker has to point at the same board
 * the Verdelers panel shows.
 *
 * The module carries `slot` (its identity of instance) next to the order number: the
 * worklist alone cannot tell two identical modules on one board apart, and the office
 * panel needs to name which one is meant.
 *
 * The resource's "model" is the payload array (`project`, `boards`), because the shape
 * is a nested projection and not a single row.
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
                ->map(fn (KnxBoard $board): array => $this->board($board))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: string, name: string, floor: string|null, modules: array<int, array{device: string, slot: int, channels: array<int, array{channel: string, links: array<int, array<string, string>>}>}>}
     */
    private function board(KnxBoard $board): array
    {
        return [
            // A board the office has not modelled (created from a field registration)
            // falls back to its short code, so it still has a stable identity.
            'id' => (string) ($board->slug ?? $board->code),
            'name' => (string) ($board->name ?? $board->code),
            'floor' => $board->floor,
            'modules' => $board->modules
                ->map(fn (KnxBoardModule $module): array => [
                    'device' => $module->device,
                    'slot' => $module->slot,
                    'channels' => $this->channels($module),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The module's links grouped by channel. `groupBy` preserves first-encounter order,
     * so the channels stay in the worklist's own order (A, B, C…), which is the order
     * the panel renders.
     *
     * @return array<int, array{channel: string, links: array<int, array<string, string>>}>
     */
    private function channels(KnxBoardModule $module): array
    {
        return $module->links
            ->groupBy('channel')
            ->map(fn ($links, $channel): array => [
                'channel' => (string) $channel,
                'links' => $links
                    ->sortBy('position')
                    ->values()
                    ->map(fn (KnxBoardLink $link): array => [
                        'room' => $link->room,
                        'object' => $link->object,
                        'role' => $link->role,
                        'ga' => $link->ga,
                        'gaName' => $link->ga_name,
                        'dpt' => $link->dpt,
                    ])
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
