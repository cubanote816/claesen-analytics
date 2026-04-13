<?php

namespace Modules\Performance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Performance\Services\EmployeePerformanceService;
use Modules\Performance\Services\PerformanceDashboardService;
use Modules\Performance\Transformers\PerformanceStatsResource;
use Modules\Performance\Transformers\EmployeeProjectEfficiencyResource;
use Modules\Cafca\Models\Employee;

class PerformanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('performance::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('performance::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('performance::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('performance::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Get performance stats for a specific employee.
     */
    public function stats(string $id, EmployeePerformanceService $service)
    {
        $employee = Employee::findOrFail($id);
        $stats = $service->getDailyStats($employee, now());
        
        return new PerformanceStatsResource($stats);
    }
 
    /**
     * Get ranking of employees based on efficiency.
     */
    public function efficiencyRanking(PerformanceDashboardService $service)
    {
        $rankings = $service->getEmployeeRanking();
        
        return EmployeeProjectEfficiencyResource::collection($rankings);
    }
}
