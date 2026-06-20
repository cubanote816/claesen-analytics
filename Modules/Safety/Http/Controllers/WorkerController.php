<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Cafca\Models\Employee;

class WorkerController extends Controller
{
    public function index(): JsonResponse
    {
        $workers = Employee::select('id', 'name')
            ->where('fl_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $workers
        ]);
    }
}
