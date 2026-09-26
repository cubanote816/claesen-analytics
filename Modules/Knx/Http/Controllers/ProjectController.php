<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\DeviceResource;
use Modules\Knx\Http\Resources\ProjectResource;
use Modules\Knx\Http\Resources\ProjectStatsResource;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\ProjectActivityService;

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

    public function show(string $code): ProjectResource
    {
        // 404 (with the contract's envelope) when the code does not exist.
        return ProjectResource::make($this->resolve($code));
    }

    public function stats(string $code): ProjectStatsResource
    {
        return ProjectStatsResource::make($this->resolve($code));
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
        return KnxProject::query()
            ->with(['client', 'lead'])
            ->where('code', $code)
            ->firstOrFail();
    }
}
