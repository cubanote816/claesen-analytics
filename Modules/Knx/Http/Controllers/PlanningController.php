<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Http\Resources\PlanningAssignmentResource;
use Modules\Knx\Http\Resources\TechnicianResource;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\PlanningService;

/**
 * Técnicos y planificación (§4.4).
 *
 * NOTE on §1.6 (zones): the contract suggests answering `PUT /planning` with a
 * structured warning when the project has zones that are not ready. That is a
 * product decision that has not been taken (see the module doc's open decisions),
 * and the office app already warns in its own UI, so this endpoint deliberately
 * still answers the plain `PlanningAssignment` the contract types.
 */
class PlanningController extends Controller
{
    /** Only field employees are technicians. */
    public function technicians(Request $request): array
    {
        $technicians = KnxEmployee::query()
            ->field()
            ->active()
            ->orderBy('id')
            ->get();

        return TechnicianResource::list($technicians, $request);
    }

    public function index(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $assignments = KnxPlanningAssignment::query()
            ->with('project')
            ->whereDate('date', '>=', $validated['from'])
            ->whereDate('date', '<=', $validated['to'])
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get();

        return PlanningAssignmentResource::list($assignments, $request);
    }

    public function store(Request $request, PlanningService $planning): PlanningAssignmentResource
    {
        $validated = $request->validate([
            'technicianId' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'projectCode' => ['required', 'string', Rule::exists('knx_projects', 'code')],
        ], [
            'projectCode.exists' => __('knx::planning.unknown_project'),
        ]);

        $employee = KnxEmployee::query()
            ->field()
            ->find($validated['technicianId']);

        if ($employee === null) {
            // Office staff, a stale id and another tenant's person all land here:
            // one message, because the caller can only act on "pick a technician".
            // Thrown as a field error (not abort()) so the front can point at the
            // control instead of showing a bare message.
            throw ValidationException::withMessages([
                'technicianId' => __('knx::planning.unknown_technician'),
            ]);
        }

        $project = KnxProject::query()->where('code', $validated['projectCode'])->sole();

        return PlanningAssignmentResource::make(
            $planning->assign($employee, $validated['date'], $project),
        );
    }

    public function destroy(Request $request, PlanningService $planning): Response
    {
        $validated = $request->validate([
            'technicianId' => ['required', 'integer'],
            'date' => ['required', 'date'],
        ]);

        $employee = KnxEmployee::query()->field()->find($validated['technicianId']);

        if ($employee !== null) {
            $planning->unassign($employee, $validated['date']);
        }

        return response()->noContent();
    }
}
