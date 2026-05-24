<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Safety\Models\Checklist;

class ChecklistController extends Controller
{
    public function active(\Illuminate\Http\Request $request): JsonResponse
    {
        $type = $request->query('type', 'inspection');

        try {
            $checklist = Checklist::with(['questions' => fn($query) => $query->orderBy('order')])
                ->where('type', $type)
                ->where('is_active', true)
                ->firstOrFail();

            return response()->json([
                'data' => $checklist
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Geen actieve checklist gevonden voor dit type.'
            ], 404);
        }
    }
}
