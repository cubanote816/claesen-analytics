<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Knx\Http\Resources\FunctionSpecResource;
use Modules\Knx\Models\KnxFunctionSpec;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\KantoorAuthService;

/**
 * Fichas funcionales (§4.B, contrato en BACKEND-API-ZONES.md §2).
 *
 * Only what the office app consumes is exposed: the two reads and the status
 * change. The document also proposes `POST /projects/{code}/functions`,
 * `POST /functions/{id}/approve` and `GET /functions/{id}/revisions`; none of them
 * is consumed by the front, and building them now would mean inventing the editing
 * flow (and the "every edit bumps `version`" rule that goes with it).
 */
class FunctionSpecController extends Controller
{
    public function index(Request $request, string $code): array
    {
        $project = KnxProject::query()->where('code', $code)->sole();

        $specs = KnxFunctionSpec::query()
            ->where('project_id', $project->getKey())
            ->with(['project', 'zone', 'author', 'approvedBy'])
            ->orderBy('id')
            ->get();

        return FunctionSpecResource::list($specs, $request);
    }

    public function update(Request $request, string $id, KantoorAuthService $auth): FunctionSpecResource
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(KnxFunctionSpec::STATUSES)],
        ]);

        $spec = KnxFunctionSpec::query()
            ->with(['project', 'zone', 'author', 'approvedBy'])
            ->findOrFail($id);

        $wasApproved = $spec->status === 'approved';
        $isApproved = $validated['status'] === 'approved';
        $user = $request->user();

        $spec->status = $validated['status'];

        // Approving is an act with a name and a date attached; un-approving takes
        // them away, because "approved by X" on a draft would be misleading.
        if ($isApproved && ! $wasApproved) {
            $spec->approved_by_employee_id = $user === null ? null : $auth->authorize($user)?->getKey();
            $spec->approved_at = now();
        } elseif (! $isApproved) {
            $spec->approved_by_employee_id = null;
            $spec->approved_at = null;
        }

        $spec->save();

        return new FunctionSpecResource($spec->refresh()->load(['project', 'zone', 'author', 'approvedBy']));
    }
}
