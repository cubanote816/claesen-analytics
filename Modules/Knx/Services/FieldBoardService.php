<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxBoardModule;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Support\IdempotentWrite;

/**
 * Creating a cabinet or a module from site (KNX-53).
 *
 * The two rules are the same the rest of the field surface follows:
 *
 *   1. **The app's `clientId` decides identity.** A retry answers the row that already
 *      exists (`200`) instead of creating a second one, and the same key in another
 *      project is a client bug (`422`), never a way to hand one project another's row.
 *   2. **What is created from site is born `unreviewed`.** The office confirms it; until
 *      then the cabinet shows in the panel marked as not yet reviewed, which is the
 *      "el campo propone, la oficina decide" rule the whole module follows.
 *
 * A slot already taken is answered by the controller as a `409 slot_taken` with the
 * module that occupies it — never a silent overwrite.
 */
class FieldBoardService
{
    public function __construct(
        private readonly FieldPhotoService $photos,
        private readonly BoardService $boards,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated input
     * @return array{board: KnxBoard, created: bool}
     */
    public function createBoard(KnxEmployee $technician, KnxProject $project, array $input): array
    {
        $replay = $this->replayBoard($project, $input['clientId']);

        if ($replay !== null) {
            return ['board' => $replay, 'created' => false];
        }

        $photoPath = $this->photos->store($input['clientId'], $input['photoDataUrl'] ?? null);

        $board = IdempotentWrite::run(
            function () use ($project, $input, $photoPath, $technician): KnxBoard {
                $board = KnxBoard::create([
                    'project_id' => $project->getKey(),
                    'slug' => $this->boards->uniqueSlug($project, (string) $input['name']),
                    'client_id' => $input['clientId'],
                    'code' => $input['code'],
                    'name' => $input['name'],
                    'floor' => $input['floor'] ?? null,
                    'photo_path' => $photoPath,
                    // Born unreviewed: the office decides.
                    'origin' => KnxBoard::ORIGIN_FIELD,
                    'review_state' => KnxBoard::REVIEW_UNREVIEWED,
                    'updated_by_employee_id' => $technician->getKey(),
                ]);

                // KNX-54: the office inbox learns about the new cabinet, typed.
                KnxNotification::create([
                    'project_id' => $project->getKey(),
                    'board_id' => $board->getKey(),
                    'kind' => KnxNotification::KIND_BOARD_CREATED,
                    'title' => __('knx::notifications.board_created'),
                    'summary' => $board->name,
                    'target_type' => 'board',
                    'target_id' => (string) ($board->slug ?? $board->code),
                    'reported_by_employee_id' => $technician->getKey(),
                    'reported_at' => Carbon::parse($input['capturedAt']),
                    'captured_at' => Carbon::parse($input['capturedAt']),
                    'acknowledged_at' => null,
                ]);

                return $board->load(['modules.links', 'modules.updatedBy', 'updatedBy']);
            },
            fn (): ?KnxBoard => $this->replayBoard($project, $input['clientId']),
        );

        return ['board' => $board, 'created' => $board->wasRecentlyCreated];
    }

    /**
     * @param  array<string, mixed>  $input  validated input
     * @return array{module: KnxBoardModule, created: bool, collision: KnxBoardModule|null}
     */
    public function addModule(KnxEmployee $technician, KnxProject $project, KnxBoard $board, array $input): array
    {
        // A replay of the same attempt returns its own module before the slot check:
        // the slot is occupied precisely by this module.
        $replay = $this->replayModule($project, $input['clientId']);

        if ($replay !== null) {
            return ['module' => $replay, 'created' => false, 'collision' => null];
        }

        $existing = $board->modules()->where('slot', $input['slot'])->first();

        if ($existing !== null) {
            return ['module' => $existing, 'created' => false, 'collision' => $existing];
        }

        $photoPath = $this->photos->store($input['clientId'], $input['photoDataUrl'] ?? null);

        $module = IdempotentWrite::run(
            function () use ($board, $input, $photoPath, $technician): KnxBoardModule {
                $module = $board->modules()->create([
                    'device' => $input['device'],
                    'slot' => (int) $input['slot'],
                    'client_id' => $input['clientId'],
                    'photo_path' => $photoPath,
                    'origin' => KnxBoardModule::ORIGIN_FIELD,
                    'review_state' => KnxBoardModule::REVIEW_UNREVIEWED,
                    'updated_by_employee_id' => $technician->getKey(),
                ]);

                KnxNotification::create([
                    'project_id' => $board->project_id,
                    'board_id' => $board->getKey(),
                    'module_slot' => $module->slot,
                    'kind' => KnxNotification::KIND_MODULE_ADDED,
                    'title' => __('knx::notifications.module_added'),
                    'summary' => trim((string) $board->name.' · slot '.$module->slot.' · '.$module->device, ' ·'),
                    'target_type' => 'module',
                    'target_id' => (string) ($board->slug ?? $board->code).'#'.$module->slot,
                    'reported_by_employee_id' => $technician->getKey(),
                    'reported_at' => Carbon::parse($input['capturedAt']),
                    'captured_at' => Carbon::parse($input['capturedAt']),
                    'acknowledged_at' => null,
                ]);

                return $module->load(['links', 'updatedBy']);
            },
            fn (): ?KnxBoardModule => $this->replayModule($project, $input['clientId']),
        );

        return ['module' => $module, 'created' => $module->wasRecentlyCreated, 'collision' => null];
    }

    /**
     * The board this `clientId` already created. Same key in another project is a client
     * bug, not a replay: answering with the stored row would give one project another's
     * cabinet.
     */
    private function replayBoard(KnxProject $project, string $clientId): ?KnxBoard
    {
        $board = KnxBoard::query()
            ->where('client_id', $clientId)
            ->with(['modules.links', 'modules.updatedBy', 'updatedBy'])
            ->first();

        if ($board === null) {
            return null;
        }

        if ((int) $board->project_id !== (int) $project->getKey()) {
            throw ValidationException::withMessages(['clientId' => [__('knx::field.client_id_reused_any')]]);
        }

        return $board;
    }

    private function replayModule(KnxProject $project, string $clientId): ?KnxBoardModule
    {
        $module = KnxBoardModule::query()
            ->where('client_id', $clientId)
            ->with(['links', 'updatedBy', 'board'])
            ->first();

        if ($module === null) {
            return null;
        }

        if ((int) $module->board->project_id !== (int) $project->getKey()) {
            throw ValidationException::withMessages(['clientId' => [__('knx::field.client_id_reused_any')]]);
        }

        return $module;
    }
}
