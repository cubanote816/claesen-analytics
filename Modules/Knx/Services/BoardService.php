<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxBoardLink;
use Modules\Knx\Models\KnxBoardModule;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxProject;

/**
 * Writing the structure of a cabinet from the office (KNX-52).
 *
 * The identity rules are the ones the read side already pinned: a board is addressed by
 * its external `id` (the worklist slug, or the slug the server derives for a board the
 * office creates) and NEVER by its numeric key, because that id is what a plan marker
 * carries; a module is addressed by its `slot`, its identity of instance.
 *
 * Every write stamps `origin = office`, `review_state = confirmed` and `updated_by`, which
 * is what tells a later re-import to leave the row alone instead of overwriting a
 * person's edit.
 */
class BoardService
{
    /**
     * The board of a project, by its external id: the slug when it has one, the short
     * code otherwise (a board created from a field registration has a code and no slug).
     */
    public function resolve(KnxProject $project, string $boardId): KnxBoard
    {
        return KnxBoard::query()
            ->where('project_id', $project->getKey())
            ->where(fn ($query) => $query->where('slug', $boardId)->orWhere('code', $boardId))
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createBoard(KnxProject $project, KnxEmployee $by, array $input): KnxBoard
    {
        $name = trim((string) ($input['name'] ?? '')) ?: (string) $input['code'];

        $board = KnxBoard::create([
            'project_id' => $project->getKey(),
            'slug' => $this->uniqueSlug($project, $name),
            'code' => $input['code'],
            'name' => $input['name'],
            'floor' => $input['floor'] ?? null,
            'origin' => KnxBoard::ORIGIN_OFFICE,
            'review_state' => KnxBoard::REVIEW_CONFIRMED,
            'updated_by_employee_id' => $by->getKey(),
        ]);

        return $this->load($board);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateBoard(KnxBoard $board, KnxEmployee $by, array $input): KnxBoard
    {
        // A rename must NOT move the slug: the id is what a plan marker points at, and
        // renaming the cabinet is not moving it on the drawing.
        if (array_key_exists('code', $input)) {
            $board->code = $input['code'];
        }

        if (array_key_exists('name', $input)) {
            $board->name = $input['name'];
        }

        if (array_key_exists('floor', $input)) {
            $board->floor = $input['floor'];
        }

        $board->origin = KnxBoard::ORIGIN_OFFICE;
        $board->review_state = KnxBoard::REVIEW_CONFIRMED;
        $board->updated_by_employee_id = $by->getKey();
        $board->save();

        return $this->load($board);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function addModule(KnxBoard $board, KnxEmployee $by, array $input): KnxBoardModule
    {
        $module = $board->modules()->create([
            'device' => $input['device'],
            'slot' => (int) $input['slot'],
            'origin' => KnxBoardModule::ORIGIN_OFFICE,
            'review_state' => KnxBoardModule::REVIEW_CONFIRMED,
            'updated_by_employee_id' => $by->getKey(),
        ]);

        return $module->load(['links', 'updatedBy']);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateModule(KnxBoardModule $module, KnxEmployee $by, array $input): KnxBoardModule
    {
        if (array_key_exists('device', $input)) {
            $module->device = $input['device'];
        }

        $module->origin = KnxBoardModule::ORIGIN_OFFICE;
        $module->review_state = KnxBoardModule::REVIEW_CONFIRMED;
        $module->updated_by_employee_id = $by->getKey();
        $module->save();

        return $module->load(['links', 'updatedBy']);
    }

    public function removeModule(KnxBoardModule $module): void
    {
        // The links cascade on the module's delete, so a module never leaves orphan links.
        $module->delete();
    }

    /**
     * Replace the links of one channel of one module. `links` replaces the set whole and
     * an empty set is valid (the channel ends up with no group address).
     *
     * @param  array<int, array<string, mixed>>  $links
     */
    public function replaceLinks(KnxBoardModule $module, KnxEmployee $by, string $channel, array $links): KnxBoardModule
    {
        DB::transaction(function () use ($module, $by, $channel, $links): void {
            $module->links()->where('channel', $channel)->delete();

            foreach ($links as $position => $link) {
                KnxBoardLink::create([
                    'module_id' => $module->getKey(),
                    'room_id' => $link['roomId'] ?? null,
                    'channel' => $channel,
                    'room' => $link['room'] ?? '',
                    'object' => $link['object'],
                    'role' => $link['role'],
                    'ga' => $link['ga'],
                    'ga_name' => $link['gaName'],
                    'dpt' => $link['dpt'],
                    'position' => $position,
                ]);
            }

            // The channel belongs to the module's structure, so editing it touches the
            // module's provenance too.
            $module->origin = KnxBoardModule::ORIGIN_OFFICE;
            $module->review_state = KnxBoardModule::REVIEW_CONFIRMED;
            $module->updated_by_employee_id = $by->getKey();
            $module->save();
        });

        return $module->refresh()->load(['links', 'updatedBy']);
    }

    public function confirmBoard(KnxBoard $board, KnxEmployee $by): KnxBoard
    {
        $board->review_state = KnxBoard::REVIEW_CONFIRMED;
        $board->updated_by_employee_id = $by->getKey();
        $board->save();

        return $this->load($board);
    }

    public function confirmModule(KnxBoardModule $module, KnxEmployee $by): KnxBoardModule
    {
        $module->review_state = KnxBoardModule::REVIEW_CONFIRMED;
        $module->updated_by_employee_id = $by->getKey();
        $module->save();

        return $module->load(['links', 'updatedBy']);
    }

    private function load(KnxBoard $board): KnxBoard
    {
        return $board->refresh()->load(['modules.links', 'modules.updatedBy', 'updatedBy']);
    }

    /**
     * A slug unique inside the project. `Str::slug` matches the identity the worklist
     * import produces, so an office-created board and an imported one are addressed the
     * same way.
     */
    public function uniqueSlug(KnxProject $project, string $name): string
    {
        $base = Str::slug($name);
        $base = $base === '' ? 'bord' : mb_substr($base, 0, 100);

        $slug = $base;
        $suffix = 2;

        while (KnxBoard::query()->where('project_id', $project->getKey())->where('slug', $slug)->exists()) {
            $slug = mb_substr($base, 0, 115).'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
