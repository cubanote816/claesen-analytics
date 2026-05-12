<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Performance\Models\Mirror\MirrorProject;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        // Usar el modelo principal de Cafca para asegurar que hay datos
        $projects = \Modules\Cafca\Models\Project::where('fl_active', true)
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $projects
        ]);
    }
}
