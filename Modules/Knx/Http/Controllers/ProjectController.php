<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\ProjectResource;
use Modules\Knx\Http\Resources\ProjectStatsResource;
use Modules\Knx\Models\KnxProject;

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

    private function resolve(string $code): KnxProject
    {
        return KnxProject::query()
            ->with(['client', 'lead'])
            ->where('code', $code)
            ->firstOrFail();
    }
}
