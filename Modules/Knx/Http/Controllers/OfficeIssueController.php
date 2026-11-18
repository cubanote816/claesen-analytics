<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\ConflictResource;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\ConflictService;
use Modules\Knx\Services\IssueService;
use Modules\Knx\Support\KnxTenant;

/**
 * `POST /projects/{code}/issues` — the office files an incident (KNX-56).
 *
 * The field already reports incidents (V11.d); this is the same finding raised from
 * the office — the client's phone call after handover is the case the ticket names.
 * It is not idempotent by a `clientId` because there is no offline retry to dedupe:
 * the office person is looking at the response.
 *
 * `kind: malfunction` is the physical installation misbehaving. It requires its
 * `symptom` (what it does wrong) and always warns as it happens; every other kind
 * keeps the ETS conflict behaviour.
 */
class OfficeIssueController extends Controller
{
    public function __construct(
        private readonly IssueService $issues,
        private readonly ConflictService $conflicts,
    ) {}

    public function store(Request $request, string $code): JsonResponse
    {
        $project = KnxProject::query()
            ->where('code', $code)
            ->where('organization_id', KnxTenant::organizationId())
            ->firstOrFail();

        /** @var KnxEmployee $employee */
        $employee = $request->attributes->get('knx_employee');

        $input = $request->validate([
            'kind' => ['required', 'string', 'in:damaged,missing,plan_mismatch,other,malfunction'],
            // What the element does wrong. Only meaningful — and only required — for a
            // malfunction; the other kinds describe what is wrong with the plan.
            'symptom' => ['required_if:kind,malfunction', 'nullable', 'string', 'max:5000'],
            'roomId' => ['sometimes', 'nullable', 'integer'],
            'deviceId' => ['sometimes', 'nullable', 'integer'],
            'boardId' => ['sometimes', 'nullable', 'string', 'max:64'],
            'moduleSlot' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'channel' => ['sometimes', 'nullable', 'string', 'max:100'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'photoDataUrl' => ['sometimes', 'nullable', 'string'],
        ]);

        $conflict = $this->issues->reportFromOffice($project, $employee, $input);

        $taken = $this->conflicts->takenAddressesFor($project, $conflict);

        return response()->json(
            (new ConflictResource($conflict, $taken))->resolve($request),
            201,
        );
    }
}
