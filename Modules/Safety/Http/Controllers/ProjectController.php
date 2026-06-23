<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Performance\Models\Mirror\MirrorProject;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = MirrorProject::where('intelligence_mirror_projects.fl_active', true)
            ->leftJoin(
                'intelligence_mirror_relations',
                'intelligence_mirror_projects.relation_id',
                '=',
                'intelligence_mirror_relations.id'
            )
            ->select([
                'intelligence_mirror_projects.id',
                'intelligence_mirror_projects.name',
                'intelligence_mirror_relations.name as relation_name',
            ])
            ->orderBy('intelligence_mirror_projects.name')
            ->get();

        return response()->json([
            'data' => $projects,
        ]);
    }
}
