<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Safety\Models\Inspection;
use Modules\Safety\Jobs\GenerateSafetyPdfJob;

class InspectionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'checklist_id'          => ['required', 'exists:safety_checklists,id'],
            'project_id'            => ['required', 'string'],
            'answers'               => ['required', 'array'],
            'answers.*.question_id' => ['required', 'exists:safety_questions,id'],
            'answers.*.status'      => ['required', 'in:ok,nok,na'],
            'answers.*.remark'      => ['nullable', 'string'],
            'answers.*.photo'       => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
        ]);

        $projectId = trim($validated['project_id']);
        $userId = $request->user()->id;

        $inspection = DB::transaction(function () use ($validated, $request, $userId, $projectId) {
            $inspection = Inspection::create([
                'user_id'      => $userId,
                'checklist_id' => $validated['checklist_id'],
                'project_id'   => $projectId,
                'completed_at' => now(),
            ]);

            foreach ($validated['answers'] as $index => $answer) {
                $photoPath = null;

                if ($request->hasFile("answers.{$index}.photo")) {
                    $file = $request->file("answers.{$index}.photo");
                    $photoPath = $file->store("safety-inspections/{$inspection->id}", 'public');
                }

                $inspection->answers()->create([
                    'question_id' => $answer['question_id'],
                    'status'      => $answer['status'],
                    'remark'      => $answer['remark'] ?? null,
                    'photo_path'  => $photoPath,
                ]);
            }

            return $inspection;
        });

        // Despachar la generación del PDF asíncronamente
        GenerateSafetyPdfJob::dispatch($inspection->id);

        return response()->json([
            'message' => 'Inspectie succesvol opgeslagen.',
            'data'    => ['inspection_id' => $inspection->id],
        ], 201);
    }
}
