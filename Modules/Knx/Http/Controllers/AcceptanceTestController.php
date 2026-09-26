<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Knx\Http\Resources\AcceptanceTestResource;
use Modules\Knx\Models\KnxAcceptanceTest;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\AcceptanceTestService;
use Modules\Knx\Services\KantoorAuthService;

/**
 * Pruebas de aceptación (§4.C, contrato en BACKEND-API-ZONES.md §3).
 *
 * The rules of recording a result live in AcceptanceTestService.
 */
class AcceptanceTestController extends Controller
{
    public function index(Request $request, string $code): array
    {
        $project = KnxProject::query()->where('code', $code)->sole();

        $tests = KnxAcceptanceTest::query()
            ->where('project_id', $project->getKey())
            ->with(['project', 'function.zone', 'executor'])
            ->orderBy('id')
            ->get();

        return AcceptanceTestResource::list($tests, $request);
    }

    public function update(
        Request $request,
        string $id,
        AcceptanceTestService $tests,
        KantoorAuthService $auth,
    ): AcceptanceTestResource {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(KnxAcceptanceTest::STATUSES)],
            'observed' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $test = KnxAcceptanceTest::query()->findOrFail($id);
        $user = $request->user();

        return new AcceptanceTestResource(
            $tests->record($test, $validated, $user === null ? null : $auth->authorize($user)),
        );
    }
}
