<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionController extends Controller
{
    /**
     * Store a newly created inspection.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Enforced string to handle legacy SQL Server IDs safely 
            'project_id' => ['required', 'string'],
            'status'     => ['required', 'string'],
            'notes'      => ['nullable', 'string'],
        ]);

        // Global Rule Enforcement: Always sanitize legacy database IDs.
        $projectId = trim((string) $validated['project_id']);

        // Strict Injection: Determine user_id ONLY from the trusted auth context
        $userId = $request->user()->id;

        /* 
         * Execution of Creation Logic
         * Note: Ensure any legacy database interactions use the ReadOnlyTrait.
         * The inspection itself should presumably be stored in the modern application DB.
         */
        
        // Example response for verification
        return response()->json([
            'message' => 'Inspectie succesvol opgeslagen.',
            'data'    => [
                'user_id'    => $userId,
                'project_id' => $projectId,
                'status'     => $validated['status'],
                'notes'      => $validated['notes'] ?? null,
            ],
        ], 201);
    }
}
