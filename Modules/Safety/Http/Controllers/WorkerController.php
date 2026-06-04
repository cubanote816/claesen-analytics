<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Core\Models\User;

class WorkerController extends Controller
{
    /**
     * Return a list of active workers (users).
     */
    public function index(): JsonResponse
    {
        // Following the requirement to return active workers. 
        // Using User model as required by the database constraints in the migration.
        $workers = User::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $workers
        ]);
    }
}
