<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers\Field;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Http\Resources\FieldIssueResultResource;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Services\FieldIssueService;
use Modules\Knx\Services\FieldProjectService;

/**
 * `POST /field/projects/{code}/issues` — an incident reported from site
 * (V11.d, CLA-609), idempotent by the app's own `clientId`.
 */
class FieldIssueController extends Controller
{
    public function __construct(
        private readonly FieldProjectService $projects,
        private readonly FieldIssueService $issues,
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
            'deviceAddress' => ['nullable', 'string', 'max:32'],
            'boardCode' => ['nullable', 'string', 'max:32'],
            'channel' => ['nullable', 'string', 'max:100'],
            // The app's own union, so an unknown value is a plain field error. `other`
            // passes here and is refused by the service with a message that explains
            // why it cannot be filed (it has no counterpart in the office contract).
            'kind' => ['required', 'string', 'in:damaged,missing,plan_mismatch,other'],
            'note' => ['required', 'string', 'max:1000'],
            'photoDataUrl' => ['nullable', 'string'],
            'capturedAt' => ['required', 'date'],
        ]);

        if ($input['projectCode'] !== $code) {
            throw ValidationException::withMessages(['projectCode' => [__('knx::field.project_mismatch')]]);
        }

        $result = $this->issues->report($technician, $project, $input);

        return response()->json(
            FieldIssueResultResource::make($result['conflict'])->resolve($request),
            $result['created'] ? 201 : 200,
        );
    }
}
