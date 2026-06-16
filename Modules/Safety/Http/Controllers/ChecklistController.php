<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Models\Question;

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

    public function show(Checklist $checklist): JsonResponse
    {
        $checklist->load([
            'questions' => fn($query) => $query
                ->with(['createdBy:id,name', 'updatedBy:id,name'])
                ->orderBy('order'),
        ]);

        $data = $checklist->toArray();
        $data['questions'] = $checklist->questions->map(fn($q) => $this->formatQuestion($q))->values();

        return response()->json(['data' => $data]);
    }

    public function active(Request $request): JsonResponse
    {
        $type = $request->query('type', 'inspection');

        try {
            $checklist = Checklist::where('type', $type)
                ->where('is_active', true)
                ->firstOrFail();

            $checklist->load([
                'questions' => fn($query) => $query
                    ->with(['createdBy:id,name', 'updatedBy:id,name'])
                    ->orderBy('order'),
            ]);

            $data = $checklist->toArray();
            $data['questions'] = $checklist->questions->map(fn($q) => $this->formatQuestion($q))->values();

            return response()->json(['data' => $data], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Geen actieve checklist gevonden voor dit type.',
            ], 404);
        }
    }

    private function formatQuestion(Question $q): array
    {
        return [
            'id'         => $q->id,
            'text_nl'    => $q->text_nl,
            'category'   => $q->category,
            'order'      => $q->order,
            'allow_yes'  => $q->allow_yes,
            'allow_no'   => $q->allow_no,
            'allow_na'   => $q->allow_na,
            'created_by' => $q->createdBy ? ['id' => $q->createdBy->id, 'name' => $q->createdBy->name] : null,
            'updated_by' => $q->updatedBy ? ['id' => $q->updatedBy->id, 'name' => $q->updatedBy->name] : null,
        ];
    }
}
