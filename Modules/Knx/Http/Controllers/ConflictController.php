<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Knx\Http\Resources\ConflictResource;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Services\ConflictService;

/**
 * El Conflictencentrum (§4.5): la lista de trabajo de oficina, el detalle y la
 * acción principal (mover el estado y/o proponer otra dirección).
 */
class ConflictController extends Controller
{
    public function index(Request $request, ConflictService $conflicts): array
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:'.implode(',', [...KnxConflict::STATUSES, 'active', 'all'])],
            'project' => ['sometimes', 'nullable', 'string'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $items = $conflicts->list(
            $validated['status'] ?? null,
            $validated['project'] ?? null,
            $validated['q'] ?? null,
        );

        $taken = $conflicts->takenAddressesForList($items);

        return $items
            ->map(fn (KnxConflict $conflict): array => (new ConflictResource($conflict, $taken[$conflict->getKey()] ?? []))
                ->resolve($request))
            ->all();
    }

    public function show(Request $request, string $id, ConflictService $conflicts): ConflictResource
    {
        $conflict = KnxConflict::query()
            ->with(['project', 'reportedBy', 'logs'])
            ->findOrFail($id);

        $taken = $conflict->project === null
            ? []
            : $conflicts->takenAddressesFor($conflict->project, $conflict);

        return new ConflictResource($conflict, $taken);
    }

    public function update(Request $request, string $id, ConflictService $conflicts): ConflictResource
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(KnxConflict::STATUSES)],
            'proposal' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);

        $conflict = KnxConflict::query()->with('project')->findOrFail($id);

        $updated = $conflicts->update(
            $conflict,
            $validated['status'] ?? null,
            $validated['proposal'] ?? null,
        );

        $taken = $updated->project === null
            ? []
            : $conflicts->takenAddressesFor($updated->project, $updated);

        return new ConflictResource($updated, $taken);
    }
}
