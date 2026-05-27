<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Safety\Models\Checklist;

class ChecklistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', Rule::in(['inspection', 'incident'])],
        ]);

        $checklists = Checklist::where('type', $request->query('type'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'is_active'])
            ->map(fn ($c) => [
                'id'        => (string) $c->id,
                'name'      => $c->name,
                'type'      => $c->type,
                'is_active' => (bool) $c->is_active,
            ]);

        return response()->json(['data' => $checklists]);
    }

    public function active(Request $request): JsonResponse
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
