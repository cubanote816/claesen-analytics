<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers\Field;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\BoardModuleResource;
use Modules\Knx\Http\Resources\BoardResource;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\BoardService;
use Modules\Knx\Services\FieldBoardService;
use Modules\Knx\Services\FieldProjectService;

/**
 * Creating cabinets and modules from site (KNX-53).
 *
 * The technician opens a cabinet and photographs what is inside; sometimes the cabinet
 * is not in the office's model yet. Both writes are idempotent by the app's own
 * `clientId` and both are born `unreviewed` — the office confirms them.
 */
class FieldBoardController extends Controller
{
    public function __construct(
        private readonly FieldProjectService $projects,
        private readonly FieldBoardService $boards,
        private readonly BoardService $boardService,
    ) {}

    public function store(Request $request, string $code): JsonResponse
    {
        $project = $this->project($request, $code);

        $validated = $request->validate([
            'clientId' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:64'],
            'photoDataUrl' => ['sometimes', 'nullable', 'string'],
            'capturedAt' => ['required', 'date'],
        ]);

        $result = $this->boards->createBoard($this->technician($request), $project, $validated);

        return response()->json(
            BoardResource::make($result['board'])->resolve($request),
            $result['created'] ? 201 : 200,
        );
    }

    public function storeModule(Request $request, string $code, string $boardId): JsonResponse
    {
        $project = $this->project($request, $code);
        $board = $this->boardService->resolve($project, $boardId);

        $validated = $request->validate([
            'clientId' => ['required', 'string', 'max:64'],
            'device' => ['required', 'string', 'max:64'],
            'slot' => ['required', 'integer', 'min:1'],
            'photoDataUrl' => ['sometimes', 'nullable', 'string'],
            'capturedAt' => ['required', 'date'],
        ]);

        $result = $this->boards->addModule($this->technician($request), $project, $board, $validated);

        // A slot somebody already modelled is an explicit conflict carrying that module:
        // the app shows it and never overwrites in silence.
        if ($result['collision'] !== null) {
            $message = __('knx::boards.slot_taken', ['slot' => $validated['slot']]);

            return response()->json([
                'message' => $message,
                'code' => 'slot_taken',
                'errors' => ['slot' => [$message]],
                'module' => ['device' => $result['collision']->device, 'slot' => (int) $result['collision']->slot],
            ], 409);
        }

        return response()->json(
            BoardModuleResource::make($result['module'])->resolve($request),
            $result['created'] ? 201 : 200,
        );
    }

    private function project(Request $request, string $code): KnxProject
    {
        return $this->projects->resolveAuthorized($this->technician($request), $code);
    }

    private function technician(Request $request): KnxEmployee
    {
        /** @var KnxEmployee $technician */
        $technician = $request->attributes->get('knx_employee');

        return $technician;
    }
}
