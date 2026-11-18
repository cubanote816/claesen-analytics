<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers\Field;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Http\Resources\FieldIssueResultResource;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Services\FieldProjectService;
use Modules\Knx\Services\IssueService;

/**
 * `POST /field/projects/{code}/issues` — an incident reported from site
 * (V11.d, CLA-609), idempotent by the app's own `clientId`.
 *
 * KNX-56 adds `kind: malfunction` (the installation misbehaving, not just damaged),
 * its `symptom`, and the optional ids (`roomId`, `deviceId`, `boardId`, `moduleSlot`)
 * the app has now that the plan and the boards have real identities. The legacy
 * `room`/`deviceAddress`/`boardCode` still work.
 */
class FieldIssueController extends Controller
{
    public function __construct(
        private readonly FieldProjectService $projects,
        private readonly IssueService $issues,
    ) {}

    public function store(Request $request, string $code): JsonResponse
    {
        /** @var KnxEmployee $technician */
        $technician = $request->attributes->get('knx_employee');

        // Scope first, exactly like the other field writes.
        $project = $this->projects->resolveAuthorized($technician, $code);

        $input = $request->validate([
            'clientId' => ['required', 'string', 'max:64'],
            'projectCode' => ['required', 'string', 'max:32'],
            // The app's own label; the project row is the authority.
            'projectName' => ['nullable', 'string', 'max:150'],
            // Everything about where: an incident is useless without a place.
            'room' => ['nullable', 'string', 'max:150'],
            'roomId' => ['nullable', 'integer'],
            'deviceAddress' => ['nullable', 'string', 'max:32'],
            'deviceId' => ['nullable', 'integer'],
            'boardCode' => ['nullable', 'string', 'max:32'],
            'boardId' => ['nullable', 'string', 'max:64'],
            'moduleSlot' => ['nullable', 'integer', 'min:1'],
            'channel' => ['nullable', 'string', 'max:100'],
            // The app's own union, so an unknown value is a plain field error. `other`
            // passes here and is filed as its own office type; `malfunction` opens the
            // malfunction lifecycle and, unlike the rest, requires a `symptom`.
            'kind' => ['required', 'string', 'in:damaged,missing,plan_mismatch,other,malfunction'],
            'symptom' => ['required_if:kind,malfunction', 'nullable', 'string', 'max:5000'],
            'note' => ['required', 'string', 'max:1000'],
            'photoDataUrl' => ['nullable', 'string'],
            'capturedAt' => ['required', 'date'],
        ]);

        if ($input['projectCode'] !== $code) {
            throw ValidationException::withMessages(['projectCode' => [__('knx::field.project_mismatch')]]);
        }

        $result = $this->issues->reportFromField($technician, $project, $input);

        return response()->json(
            FieldIssueResultResource::make($result['conflict'])->resolve($request),
            $result['created'] ? 201 : 200,
        );
    }
}
