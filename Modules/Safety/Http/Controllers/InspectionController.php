<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Safety\Models\Inspection;
use Modules\Safety\Jobs\GenerateSafetyPdfJob;
use Filament\Notifications\Notification;
use Modules\Core\Models\User;

class InspectionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // 1. Pre-process JSON answers if sent as string (common in FormData)
        if ($request->has('answers') && is_string($request->get('answers'))) {
            $request->merge([
                'answers' => json_decode($request->get('answers'), true)
            ]);
        }

        $validated = $request->validate([
            'checklist_id'          => ['required', 'exists:safety_checklists,id'],
            'project_id'            => ['required', 'string', 'exists:intelligence_mirror_projects,id'],
            'answers'               => ['required', 'array'],
            'answers.*.question_id' => ['required', 'exists:safety_questions,id'],
            'answers.*.value'       => ['required', 'in:YES,NO,NA'], // Map from Frontend values
            'answers.*.remark'      => ['nullable', 'string'],
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

            foreach ($validated['answers'] as $answer) {
                $questionId = $answer['question_id'];
                $photoPath = null;

                // Map Frontend status to Backend status
                $statusMap = [
                    'YES' => 'ok',
                    'NO'  => 'nok',
                    'NA'  => 'na'
                ];

                // Check for photo in the top-level 'photos' array (sent as photos[question_id])
                if ($request->hasFile("photos.{$questionId}")) {
                    $file = $request->file("photos.{$questionId}");
                    $photoPath = $file->store("safety-inspections/{$inspection->id}", 'public');
                }

                $inspection->answers()->create([
                    'question_id' => $questionId,
                    'status'      => $statusMap[$answer['value']] ?? 'na',
                    'remark'      => $answer['remark'] ?? null,
                    'photo_path'  => $photoPath,
                ]);
            }

            return $inspection;
        });

        // Despachar la generación del PDF asíncronamente
        GenerateSafetyPdfJob::dispatch($inspection->id);

        // Notificar a los Super Admins
        $admins = User::role('super_admin')->get();
        if ($admins->count() > 0) {
            $notification = Notification::make()
                ->title('Nieuwe werkplekinspectie')
                ->icon('heroicon-o-shield-check')
                ->body("Inspecteur **{$request->user()->name}** heeft een inspectie ingediend for project **{$projectId}**.")
                ->success()
                ->viewData(['module' => 'safety'])
                ->actions([
                    \Filament\Actions\Action::make('view')
                        ->label('Bekijken')
                        ->url(\Modules\Safety\Filament\Resources\InspectionResource::getUrl('view', ['record' => $inspection]))
                ]);

            foreach ($admins as $admin) {
                $admin->notifyNow($notification->toDatabase());
            }
        }

        return response()->json([
            'message' => 'Inspectie succesvol opgeslagen.',
            'data'    => ['inspection_id' => $inspection->id],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $inspections = Inspection::where('user_id', $request->user()->id)
            ->orderBy('completed_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($inspection) {
                // Determine overall status based on answers
                $hasDefects = $inspection->answers->contains('status', 'nok');
                
                return [
                    'id'      => $inspection->id,
                    'project' => $inspection->project_id,
                    'date'    => $inspection->completed_at ? $inspection->completed_at->format('Y-m-d H:i') : now()->format('Y-m-d H:i'),
                    'status'  => $hasDefects ? 'DEFECTEN' : 'VEILIG',
                    'type'    => $hasDefects ? 'warning' : 'success',
                ];
            });

        return response()->json([
            'data' => $inspections
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $totalInspections = Inspection::where('user_id', $userId)->count();
        $defectsCount = Inspection::where('user_id', $userId)
            ->whereHas('answers', function ($query) {
                $query->where('status', 'nok');
            })->count();

        return response()->json([
            'data' => [
                'total'   => $totalInspections,
                'defects' => $defectsCount,
            ]
        ]);
    }
}
