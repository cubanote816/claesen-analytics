<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxBoardLink;
use Modules\Knx\Models\KnxBoardModule;

/**
 * One module of a cabinet (KNX-52), with its provenance.
 *
 * `slot` is the module's identity of instance on the board; `origin`/`reviewState` say
 * who wrote it and whether the office accepted it (a field creation is born
 * `unreviewed`). The links are grouped by channel, in the worklist's own order.
 *
 * @property-read KnxBoardModule $resource
 */
class BoardModuleResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        $module = $this->resource;

        return [
            'device' => $module->device,
            'slot' => (int) $module->slot,
            'origin' => $module->origin,
            'reviewState' => $module->review_state,
            'updatedBy' => $module->updatedBy?->shortName(),
            'updatedAt' => $module->updated_at?->toIso8601String(),
            'channels' => $this->channels($module),
        ];
    }

    /**
     * @return array<int, array{channel: string, links: array<int, array<string, string|null>>}>
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
                        'roomId' => $link->room_id === null ? null : (string) $link->room_id,
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
