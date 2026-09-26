<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Http\Resources\ExportRecordResource;
use Modules\Knx\Jobs\GenerateKnxExportJob;
use Modules\Knx\Models\KnxExport;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\KantoorAuthService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rapporten & export (§4.9).
 */
class ReportController extends Controller
{
    public function index(Request $request): array
    {
        $exports = KnxExport::query()
            ->with('project')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return ExportRecordResource::list($exports, $request);
    }

    /**
     * Queues the report and answers `202` straight away: §4.9 is explicit that the
     * request must not wait for the file.
     */
    public function store(Request $request, KantoorAuthService $auth): JsonResponse
    {
        $validated = $request->validate([
            'projectCode' => ['required', 'string', Rule::exists('knx_projects', 'code')],
            'type' => ['required', 'string', Rule::in(KnxExport::TYPES)],
        ], [
            'projectCode.exists' => __('knx::reports.unknown_project'),
        ]);

        if ($validated['type'] === KnxExport::TYPE_HOURS) {
            // The contract lists it, and it is the one type this domain cannot
            // produce: nothing tracks hours (Veld does not report time yet).
            // Refusing loudly beats shipping a file full of invented numbers.
            throw ValidationException::withMessages([
                'type' => __('knx::reports.hours_unavailable'),
            ]);
        }

        $project = KnxProject::query()->where('code', $validated['projectCode'])->sole();
        $user = $request->user();

        $export = KnxExport::create([
            'project_id' => $project->getKey(),
            'type' => $validated['type'],
            'status' => KnxExport::STATUS_QUEUED,
            'created_by_employee_id' => $user === null ? null : $auth->authorize($user)?->getKey(),
        ]);

        GenerateKnxExportJob::dispatch($export->getKey());

        return ExportRecordResource::make($export->refresh()->load('project'))
            ->response()
            ->setStatusCode(202);
    }

    /** @see DocumentController::download() for why this is `signed` and not `auth`. */
    public function download(string $id): StreamedResponse
    {
        $export = KnxExport::query()->findOrFail($id);
        $path = $export->path;

        abort_if(
            $export->status !== KnxExport::STATUS_READY || $path === null || ! Storage::disk('local')->exists($path),
            404,
            __('knx::reports.file_not_ready'),
        );

        return Storage::disk('local')->download($path);
    }
}
