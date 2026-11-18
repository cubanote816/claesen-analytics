<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Knx\Http\Resources\DeviceResource;
use Modules\Knx\Http\Resources\ProjectResource;
use Modules\Knx\Http\Resources\ProjectStatsResource;
use Modules\Knx\Http\Resources\VisitResource;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxVisit;
use Modules\Knx\Services\ProjectActivityService;
use Modules\Knx\Services\ProjectService;
use Modules\Knx\Support\KnxTenant;

/**
 * Proyectos (§4.3). Projects are addressed by their natural key (`code`), never
 * by the numeric id — that is how the office app refers to them everywhere.
 */
class ProjectController extends Controller
{
    public function index(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:'.implode(',', [...KnxProject::STATUSES, 'all'])],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $status = $validated['status'] ?? null;

        $projects = KnxProject::query()
            ->with(['client', 'lead'])
            ->when($status !== null && $status !== 'all', fn ($query) => $query->status($status))
            ->search($validated['q'] ?? null)
            // The contract fixes no order for projects, so this keeps the stable
            // insertion order the office app's fixture shows.
            ->orderBy('id')
            ->get();

        return ProjectResource::list($projects, $request);
    }

    public function store(Request $request, ProjectService $projects): JsonResponse
    {
        // `status` and the device/photo counters are deliberately absent: a new
        // project starts empty and only the field work fills them. `organization_id`
        // is never accepted either — the tenant trait fills it from the session's
        // organization, like every other write in this module.
        // `clientId` and `leadEmployeeId` are scoped to this organization. Without
        // that scope an office user could attach another tenant's client or employee
        // to their own project, and the response would load the foreign row — the
        // message says "unknown client", so the rule has to make it true.
        $validated = $request->validate([
            'clientId' => [
                'required',
                'integer',
                Rule::exists('knx_clients', 'id')->where('organization_id', KnxTenant::organizationId()),
            ],
            // `code` is the natural key, unique per organization (the DB enforces it
            // too; this rule is what turns the clash into a field error, not a 500).
            // The pattern is the read route's own constraint: a code with a space
            // would be created with a 201 and then be unreachable, because
            // `GET /projects/{code}` only matches [A-Za-z0-9._-]+. Rejecting it here
            // is the missing 422, not a design change.
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('knx_projects', 'code')->where('organization_id', KnxTenant::organizationId()),
            ],
            'name' => ['required', 'string', 'max:255'],
            // `siteAddress` is the structured address the contract requires (KNX-51).
            // `city` stays accepted for the clients that still send it, but the value
            // that lands on the row is the one inside `siteAddress`: keeping two
            // sources for the same fact is how they end up disagreeing.
            'siteAddress' => ['required', 'array'],
            'siteAddress.street' => ['required', 'string', 'max:255'],
            'siteAddress.postalCode' => ['required', 'string', 'max:16'],
            'siteAddress.city' => ['required', 'string', 'max:255'],
            'siteAddress.accessNote' => ['sometimes', 'nullable', 'string', 'max:500'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'leadEmployeeId' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('knx_employees', 'id')->where('organization_id', KnxTenant::organizationId()),
            ],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'rooms' => ['sometimes', 'array'],
            'rooms.*.name' => ['required', 'string', 'max:255'],
            'rooms.*.floor' => ['sometimes', 'nullable', 'string', 'max:255'],
        ], [
            'code.unique' => __('knx::projects.duplicate_code'),
            'code.regex' => __('knx::projects.invalid_code'),
            'clientId.exists' => __('knx::projects.unknown_client'),
            'leadEmployeeId.exists' => __('knx::projects.unknown_lead'),
        ]);

        $project = $projects->create(
            [
                // Contract keys are camelCase, the columns are snake_case.
                'client_id' => $validated['clientId'],
                'code' => $validated['code'],
                'name' => $validated['name'],
                // Derived from the structured address, never from a loose `city`.
                'city' => $validated['siteAddress']['city'],
                'site_street' => $validated['siteAddress']['street'],
                'site_postal_code' => $validated['siteAddress']['postalCode'],
                'site_city' => $validated['siteAddress']['city'],
                'site_access_note' => $validated['siteAddress']['accessNote'] ?? null,
                'lead_employee_id' => $validated['leadEmployeeId'] ?? null,
                'deadline' => $validated['deadline'] ?? null,
            ],
            $validated['rooms'] ?? [],
        );

        // The read shape the office already knows (§4.3), so the front needs no
        // second parser for a created project.
        return response()->json(
            ProjectResource::make($project->load(['client', 'lead']))->resolve($request),
            201,
        );
    }

    public function show(string $code): ProjectResource
    {
        // 404 (with the contract's envelope) when the code does not exist.
        return ProjectResource::make($this->resolve($code));
    }

    /**
     * Edit the project header (KNX-51): name, lead, deadline and site address.
     *
     * Partial by design — only the keys that come are touched. `siteAddress` replaces
     * the whole block when it comes (its three fields are required together), and its
     * `city` is copied onto the derived `city` column so the two never disagree.
     */
    public function update(Request $request, string $code): ProjectResource
    {
        $project = $this->resolve($code);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'leadEmployeeId' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('knx_employees', 'id')->where('organization_id', KnxTenant::organizationId()),
            ],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'siteAddress' => ['sometimes', 'array'],
            'siteAddress.street' => ['required_with:siteAddress', 'string', 'max:255'],
            'siteAddress.postalCode' => ['required_with:siteAddress', 'string', 'max:16'],
            'siteAddress.city' => ['required_with:siteAddress', 'string', 'max:255'],
            'siteAddress.accessNote' => ['sometimes', 'nullable', 'string', 'max:500'],
        ], [
            'leadEmployeeId.exists' => __('knx::projects.unknown_lead'),
        ]);

        $attributes = [];

        if (array_key_exists('name', $validated)) {
            $attributes['name'] = $validated['name'];
        }

        if (array_key_exists('leadEmployeeId', $validated)) {
            $attributes['lead_employee_id'] = $validated['leadEmployeeId'];
        }

        if (array_key_exists('deadline', $validated)) {
            $attributes['deadline'] = $validated['deadline'];
        }

        if (array_key_exists('siteAddress', $validated)) {
            $attributes['site_street'] = $validated['siteAddress']['street'];
            $attributes['site_postal_code'] = $validated['siteAddress']['postalCode'];
            $attributes['site_city'] = $validated['siteAddress']['city'];
            $attributes['site_access_note'] = $validated['siteAddress']['accessNote'] ?? null;
            $attributes['city'] = $validated['siteAddress']['city'];
        }

        if ($attributes !== []) {
            $project->fill($attributes)->save();
        }

        return ProjectResource::make($project->load(['client', 'lead']));
    }

    public function stats(string $code): ProjectStatsResource
    {
        return ProjectStatsResource::make($this->resolve($code));
    }

    /**
     * The closures signed off from site (V11.e, CLA-609), newest first.
     *
     * The office is the reader of a closure, never its author: the field app sends
     * them and never reads them back. A `final` here does NOT change the project's
     * status — that stays an office decision about its own records.
     */
    public function visits(Request $request, string $code): array
    {
        $project = $this->resolve($code);

        $visits = KnxVisit::query()
            ->where('project_id', $project->getKey())
            ->with(['room', 'closedBy', 'items', 'project'])
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->get();

        return VisitResource::list($visits, $request);
    }

    /**
     * The project's dossier: every registered device, field registrations first
     * (that is the contract's order) and, inside each group, by address.
     *
     * `hasConflict` needs the project's live conflict addresses; they are fetched
     * once for the whole list rather than per device.
     */
    public function devices(Request $request, string $code): array
    {
        $project = $this->resolve($code);

        $conflictAddresses = KnxConflict::query()
            ->where('project_id', $project->id)
            ->open()
            ->pluck('address')
            ->all();

        $devices = $project->devices()
            ->with(['room', 'board', 'registeredBy'])
            ->get()
            // Field registrations first (also the unacknowledged ones, which the UI
            // flags as new), then the ETS plan, each group in address order. Done in
            // PHP so it does not depend on a database-specific ORDER BY FIELD().
            ->sortBy(fn ($device): array => [
                $device->source === KnxDevice::SOURCE_FIELD ? 0 : 1,
                $device->address,
            ])
            ->values();

        // Built by hand rather than through ::list(): that helper wraps *models*
        // in resources, and each device here needs the project's conflict
        // addresses passed in. resolve() gives the same plain array the other
        // endpoints return.
        return $devices
            ->map(fn (KnxDevice $device): array => (new DeviceResource($device, $conflictAddresses))->resolve($request))
            ->all();
    }

    public function activity(Request $request, string $code, ProjectActivityService $activity): array
    {
        return $activity->forProject($this->resolve($code));
    }

    private function resolve(string $code): KnxProject
    {
        // The tenant boundary is explicit here, not inherited from a trait: the model
        // deliberately has no global scope (one tenant, and a scope would hide "no
        // rows" bugs). Without this, two organizations sharing a project code would
        // resolve each other's row.
        return KnxProject::query()
            ->with(['client', 'lead'])
            ->where('code', $code)
            ->where('organization_id', KnxTenant::organizationId())
            ->firstOrFail();
    }
}
