<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Safety\Models\Checklist;

class ChecklistController extends Controller
{
    public function active(): JsonResponse
    {
        $checklist = Checklist::with(['questions' => fn($query) => $query->orderBy('order')])
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'data' => $checklist
        ], 200);
    }
}
