<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Safety\Services\ComplianceService;

class ComplianceController extends Controller
{
    public function __construct(private readonly ComplianceService $compliance) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $missing = $this->compliance->getMissingInspections();

        $data = $missing->values()->map(fn ($project) => [
            'project_id' => $project->id,
            'name'       => $project->name,
        ]);

        return response()->json([
            'data'  => $data,
            'count' => $data->count(),
        ]);
    }
}
