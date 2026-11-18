<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Knx\Http\Resources\BoardModuleResource;
use Modules\Knx\Http\Resources\BoardResource;
use Modules\Knx\Http\Resources\ProjectBoardsResource;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxBoardModule;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\BoardService;
use Modules\Knx\Support\KnxTenant;

/**
 * The cabinets of a project (KNX-3 read, KNX-52 write).
 *
 * The office's Verdelers tab used to be fed by a generated import of the project's
 * worklist. This endpoint serves the same data from the database, and its board `id` is
 * the identity a plan marker points at.
 *
 * The write side exists because the office sometimes has to model a cabinet by hand (a
 * cabinet the worklist never carried, a module added on site). Every write stamps its
 * provenance so a later re-import can leave it alone.
 *
 * A project whose worklist has not been imported answers with an empty `boards` array:
 * that is a real state (nothing to show yet), not an error.
 */
class BoardController extends Controller
{
    public function __construct(private readonly BoardService $boards) {}

    public function index(Request $request, string $code): array
    {
        $project = $this->project($code);

        $boards = KnxBoard::query()
            ->where('project_id', $project->getKey())
            ->with(['modules.links', 'modules.updatedBy', 'updatedBy'])
            ->orderBy('id')
            ->get();

        return ProjectBoardsResource::make([
            'project' => $project,
            'boards' => $boards,
        ])->resolve($request);
    }

    public function store(Request $request, string $code): JsonResponse
    {
        $project = $this->project($code);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $board = $this->boards->createBoard($project, $this->employee($request), $validated);

        return response()->json(
            BoardResource::make($board)->resolve($request),
            201,
        );
    }

    public function update(Request $request, string $code, string $boardId): BoardResource
    {
        $board = $this->boards->resolve($this->project($code), $boardId);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:32'],
            'name' => ['sometimes', 'string', 'max:255'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        return BoardResource::make($this->boards->updateBoard($board, $this->employee($request), $validated));
    }

    public function storeModule(Request $request, string $code, string $boardId): JsonResponse
    {
        $board = $this->boards->resolve($this->project($code), $boardId);

        $validated = $request->validate([
            'device' => ['required', 'string', 'max:64'],
            'slot' => ['required', 'integer', 'min:1'],
        ]);

        // A taken slot is an explicit conflict carrying the module that occupies it:
        // never a silent overwrite of a module somebody already modelled.
        $existing = $board->modules()->where('slot', $validated['slot'])->first();

        if ($existing !== null) {
            return response()->json([
                'message' => __('knx::boards.slot_taken', ['slot' => $validated['slot']]),
                'code' => 'slot_taken',
                'errors' => ['slot' => [__('knx::boards.slot_taken', ['slot' => $validated['slot']])]],
                'module' => ['device' => $existing->device, 'slot' => (int) $existing->slot],
            ], 409);
        }

        $module = $this->boards->addModule($board, $this->employee($request), $validated);

        return response()->json(BoardModuleResource::make($module)->resolve($request), 201);
    }

    public function updateModule(Request $request, string $code, string $boardId, int $slot): BoardModuleResource
    {
        $module = $this->module($code, $boardId, $slot);

        $validated = $request->validate([
            'device' => ['sometimes', 'string', 'max:64'],
        ]);

        return BoardModuleResource::make($this->boards->updateModule($module, $this->employee($request), $validated));
    }

    public function destroyModule(string $code, string $boardId, int $slot): JsonResponse
    {
        $this->boards->removeModule($this->module($code, $boardId, $slot));

        return response()->json(null, 204);
    }

    public function updateLinks(Request $request, string $code, string $boardId, int $slot, string $channel): BoardModuleResource
    {
        $project = $this->project($code);
        $module = $this->moduleOf($project, $boardId, $slot);

        $validated = $request->validate([
            // `present`, not `required`: emptying a channel's links is valid.
            'links' => ['present', 'array', 'max:200'],
            'links.*.roomId' => [
                'required',
                Rule::exists('knx_project_rooms', 'id')->where('project_id', $project->getKey()),
            ],
            'links.*.room' => ['sometimes', 'nullable', 'string', 'max:150'],
            'links.*.object' => ['required', 'string', 'max:150'],
            'links.*.role' => ['required', 'string', 'max:64'],
            'links.*.ga' => ['required', 'string', 'max:32'],
            'links.*.gaName' => ['required', 'string', 'max:255'],
            'links.*.dpt' => ['required', 'string', 'max:32'],
        ]);

        $module = $this->boards->replaceLinks($module, $this->employee($request), $channel, $validated['links']);

        return BoardModuleResource::make($module);
    }

    public function confirm(Request $request, string $code, string $boardId): BoardResource
    {
        $board = $this->boards->resolve($this->project($code), $boardId);

        return BoardResource::make($this->boards->confirmBoard($board, $this->employee($request)));
    }

    public function confirmModule(Request $request, string $code, string $boardId, int $slot): BoardModuleResource
    {
        $module = $this->module($code, $boardId, $slot);

        return BoardModuleResource::make($this->boards->confirmModule($module, $this->employee($request)));
    }

    private function module(string $code, string $boardId, int $slot): KnxBoardModule
    {
        return $this->moduleOf($this->project($code), $boardId, $slot);
    }

    private function moduleOf(KnxProject $project, string $boardId, int $slot): KnxBoardModule
    {
        $board = $this->boards->resolve($project, $boardId);

        return $board->modules()->where('slot', $slot)->firstOrFail();
    }

    private function employee(Request $request): KnxEmployee
    {
        /** @var KnxEmployee $employee */
        $employee = $request->attributes->get('knx_employee');

        return $employee;
    }

    private function project(string $code): KnxProject
    {
        return KnxProject::query()
            ->where('code', $code)
            ->where('organization_id', KnxTenant::organizationId())
            ->firstOrFail();
    }
}
