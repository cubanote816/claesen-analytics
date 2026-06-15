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
        $user = $request->user();

        if (! $user->hasAnyRole(['super_admin', 'admin', 'project_manager'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $projects = $this->compliance->getNonCompliantProjects();

        return response()->json([
            'data'  => $projects->values(),
            'count' => $projects->count(),
        ]);
    }
}
