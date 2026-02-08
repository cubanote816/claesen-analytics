<?php

namespace Modules\Website\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Website\Services\PortfolioService;

class ProjectController extends Controller
{
    protected $portfolioService;

    public function __construct(PortfolioService $portfolioService)
    {
        $this->portfolioService = $portfolioService;
    }

    public function index(Request $request)
    {
        $projects = $this->portfolioService->getProjects(
            $request->all(),
            $request->input('per_page', 12)
        );

        return response()->json($projects);
    }

    public function show($slug)
    {
        $project = $this->portfolioService->getProjectBySlug($slug);

        // Append gallery to the response
        $project->gallery = $this->portfolioService->getProjectGallery($project);
        $project->related = $this->portfolioService->getRelatedProjects($project);

        return response()->json([
            'data' => $project
        ]);
    }

    public function categories()
    {
        return response()->json([
            'data' => $this->portfolioService->getCategories()
        ]);
    }

    public function years()
    {
        return response()->json([
            'data' => $this->portfolioService->getYears()
        ]);
    }
}
